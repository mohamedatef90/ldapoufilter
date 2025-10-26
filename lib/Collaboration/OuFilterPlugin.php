<?php
declare(strict_types=1);

namespace OCA\LdapOuFilter\Collaboration;

use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Search\SearchResultType;
use OCP\Share\IShare;
use OCA\LdapOuFilter\Service\LdapOuService;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Filters user search results to only show users from the same OU
 * This hooks directly into Nextcloud's Collaborator Search system
 */
class OuFilterPlugin implements ISearchPlugin {
    private LdapOuService $ldapOuService;
    private IUserSession $userSession;
    private LoggerInterface $logger;

    public function __construct(
        LdapOuService $ldapOuService,
        IUserSession $userSession,
        LoggerInterface $logger
    ) {
        $this->ldapOuService = $ldapOuService;
        $this->userSession = $userSession;
        $this->logger = $logger;
    }

    /**
     * Search for sharees (users, groups, etc.)
     * This is called by Nextcloud when searching for users to share with
     */
    public function search($search, $limit, $offset, ISearchResult $searchResult): bool {
        $this->logger->info('=== OU Filter Plugin ACTIVATED ===', ['app' => 'ldapoufilter']);
        $this->logger->info("Search query: $search, limit: $limit, offset: $offset", ['app' => 'ldapoufilter']);

        // Get current user
        $currentUser = $this->userSession->getUser();
        if (!$currentUser) {
            $this->logger->warning('No current user in session', ['app' => 'ldapoufilter']);
            return false; // Don't modify results
        }

        $currentUserId = $currentUser->getUID();
        $this->logger->info("Current user: $currentUserId", ['app' => 'ldapoufilter']);
        
        // Get current user's OU
        $currentUserOu = $this->ldapOuService->getUserOu($currentUserId);
        if (!$currentUserOu) {
            $this->logger->warning("No OU found for current user: $currentUserId", ['app' => 'ldapoufilter']);
            return false;
        }
        
        $this->logger->info("Current user OU: $currentUserOu", ['app' => 'ldapoufilter']);

        // Filter users in the search results
        $filtered = $this->filterSearchResultType($searchResult, 'users', $currentUserId, $currentUserOu);
        
        if ($filtered) {
            $this->logger->info("Successfully filtered search results", ['app' => 'ldapoufilter']);
        }

        return false; // Return false to allow other plugins to process too
    }

    /**
     * Filter a specific result type (users or exact matches)
     */
    private function filterSearchResultType(ISearchResult $searchResult, string $type, string $currentUserId, string $currentUserOu): bool {
        try {
            // Get the result type
            $searchArray = $searchResult->asArray();
            $usersData = $searchArray['users'] ?? [];
            
            // Handle different array structures - get both 'results' and 'exact' arrays
            $results = [];
            $exactResults = [];
            
            if (is_array($usersData)) {
                if (isset($usersData['results'])) {
                    $results = $usersData['results'];
                } else {
                    $results = $usersData;
                }
                
                // Also get the exact matches array
                if (isset($usersData['exact'])) {
                    $exactResults = $usersData['exact'];
                }
            }

            if (empty($results) && empty($exactResults)) {
                $this->logger->info("No results to filter", ['app' => 'ldapoufilter']);
                return false;
            }

            $originalCount = count($results) + count($exactResults);
            $this->logger->info("Filtering $originalCount results (results: " . count($results) . ", exact: " . count($exactResults) . ")", ['app' => 'ldapoufilter']);

            // Build filtered arrays - only include users in same OU
            $filteredResults = [];
            $filteredExact = [];
            $filteredCount = 0;
            $errorsDuringFiltering = 0;
            
            // Function to check and filter a single result
            $checkAndAdd = function($result, &$targetArray, &$count) use ($currentUserOu, $currentUserId) {
                $userId = null;
                if (is_array($result)) {
                    $userId = $result['value']['shareWith'] ?? 
                              $result['value']['name'] ?? 
                              $result['shareWith'] ?? 
                              $result['name'] ??
                              null;
                }
                
                if (!$userId) {
                    $this->logger->debug("Skipping result without userId", ['app' => 'ldapoufilter']);
                    return;
                }

                $this->logger->debug("Checking user: $userId", ['app' => 'ldapoufilter']);

                try {
                    // Check if this user is in the same OU
                    $userOu = $this->ldapOuService->getUserOu($userId);
                    $this->logger->debug("  User OU: " . ($userOu ?: 'none'), ['app' => 'ldapoufilter']);

                    if ($userOu && $userOu === $currentUserOu) {
                        // Same OU - keep in filtered results
                        $targetArray[] = $result;
                        $count++;
                        $this->logger->debug("✓ User $userId kept (same OU: $userOu)", ['app' => 'ldapoufilter']);
                    } else {
                        $this->logger->debug("✗ User $userId filtered out (current OU: $currentUserOu, user OU: " . ($userOu ?: 'none') . ")", ['app' => 'ldapoufilter']);
                    }
                } catch (\Exception $e) {
                    $errorsDuringFiltering++;
                    $this->logger->warning("Error checking OU for user $userId: " . $e->getMessage(), ['app' => 'ldapoufilter']);
                }
            };
            
            // Filter the regular results
            foreach ($results as $result) {
                $checkAndAdd($result, $filteredResults, $filteredCount);
            }
            
            // Filter the exact matches
            foreach ($exactResults as $exactResult) {
                $checkAndAdd($exactResult, $filteredExact, $filteredCount);
            }

            // Only update if we found at least one matching user in the same OU
            // This prevents returning empty results (which causes 500 errors)
            if ($filteredCount > 0 && $filteredCount < $originalCount) {
                // Use unsetResult and addResultSet to replace the results (both regular and exact)
                $searchResult->unsetResult(SearchResultType::USER);
                $searchResult->addResultSet(SearchResultType::USER, $filteredResults, $filteredExact);
                $this->logger->info("==> Filtered results: $originalCount -> $filteredCount users (regular: " . count($filteredResults) . ", exact: " . count($filteredExact) . ")", ['app' => 'ldapoufilter']);
            } elseif ($filteredCount === 0) {
                // No users in same OU found - return empty results but don't throw error
                $searchResult->unsetResult(SearchResultType::USER);
                $searchResult->addResultSet(SearchResultType::USER, [], []);
                $this->logger->info("==> No users in same OU found (filtered out all $originalCount users)", ['app' => 'ldapoufilter']);
            } else {
                // All users already in same OU - no filtering needed
                $this->logger->info("==> All $originalCount users are in same OU (no filtering needed)", ['app' => 'ldapoufilter']);
            }
            
            return true;

        } catch (\Exception $e) {
            $this->logger->error("Error filtering results: " . $e->getMessage(), [
                'app' => 'ldapoufilter',
                'exception' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}

