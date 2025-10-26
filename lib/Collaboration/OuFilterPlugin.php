<?php
declare(strict_types=1);

namespace OCA\LdapOuFilter\Collaboration;

use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
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
            
            // Handle different array structures
            $results = [];
            if (is_array($usersData) && isset($usersData['results'])) {
                $results = $usersData['results'];
            } elseif (is_array($usersData)) {
                $results = $usersData;
            }

            if (empty($results)) {
                $this->logger->info("No results to filter", ['app' => 'ldapoufilter']);
                return false;
            }

            $originalCount = count($results);
            $this->logger->info("Filtering $originalCount results", ['app' => 'ldapoufilter']);

            // Build filtered array - only include users in same OU
            $filteredResults = [];
            $filteredCount = 0;
            $errorsDuringFiltering = 0;
            
            foreach ($results as $result) {
                // Try multiple ways to get the user ID
                $userId = null;
                if (is_array($result)) {
                    $userId = $result['value']['shareWith'] ?? 
                              $result['value']['name'] ?? 
                              $result['shareWith'] ?? 
                              $result['name'] ??
                              null;
                }
                
                if (!$userId) {
                    $this->logger->debug("Skipping result without userId", ['app' => 'ldapoufilter', 'result' => json_encode($result)]);
                    continue;
                }

                $this->logger->debug("Checking user: $userId", ['app' => 'ldapoufilter']);

                try {
                    // Check if this user is in the same OU
                    $userOu = $this->ldapOuService->getUserOu($userId);
                    $this->logger->debug("  User OU: " . ($userOu ?: 'none'), ['app' => 'ldapoufilter']);

                    if ($userOu && $userOu === $currentUserOu) {
                        // Same OU - keep in filtered results
                        $filteredResults[] = $result;
                        $filteredCount++;
                        $this->logger->debug("✓ User $userId kept (same OU: $userOu)", ['app' => 'ldapoufilter']);
                    } else {
                        $this->logger->debug("✗ User $userId filtered out (current OU: $currentUserOu, user OU: " . ($userOu ?: 'none') . ")", ['app' => 'ldapoufilter']);
                    }
                } catch (\Exception $e) {
                    $errorsDuringFiltering++;
                    $this->logger->warning("Error checking OU for user $userId: " . $e->getMessage(), ['app' => 'ldapoufilter']);
                    // Don't add this user to results
                }
            }

            // Only update if we found at least one matching user in the same OU
            // This prevents returning empty results (which causes 500 errors)
            if ($filteredCount > 0 && $filteredCount < $originalCount) {
                // Use unsetResult and addResultSet to replace the results
                $searchResult->unsetResult(SearchResultType::User);
                $searchResult->addResultSet(SearchResultType::User, $filteredResults, []);
                $this->logger->info("==> Filtered results: $originalCount -> $filteredCount users", ['app' => 'ldapoufilter']);
            } elseif ($filteredCount === 0) {
                // No users in same OU found - return empty results but don't throw error
                $searchResult->unsetResult(SearchResultType::User);
                $searchResult->addResultSet(SearchResultType::User, [], []);
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

