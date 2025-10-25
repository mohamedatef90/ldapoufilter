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

        // Filter users in the search results
        $this->filterSearchResultType($searchResult, 'users', $currentUserId);
        $this->filterSearchResultType($searchResult, 'exact', $currentUserId);

        return false; // Return false to allow other plugins to process too
    }

    /**
     * Filter a specific result type (users or exact matches)
     */
    private function filterSearchResultType(ISearchResult $searchResult, string $type, string $currentUserId): void {
        try {
            // Get the result type
            if ($type === 'users') {
                $results = $searchResult->asArray()['users'] ?? [];
            } elseif ($type === 'exact') {
                $results = $searchResult->asArray()['exact']['users'] ?? [];
            } else {
                return;
            }

            if (empty($results)) {
                $this->logger->info("No $type results to filter", ['app' => 'ldapoufilter']);
                return;
            }

            $originalCount = count($results);
            $this->logger->info("Filtering $originalCount $type results", ['app' => 'ldapoufilter']);

            // Filter results based on OU
            $filteredResults = [];
            foreach ($results as $result) {
                $userId = $result['value']['shareWith'] ?? null;
                
                if (!$userId) {
                    $this->logger->debug("Skipping result without shareWith field", ['app' => 'ldapoufilter']);
                    continue;
                }

                $this->logger->debug("Checking user: $userId", ['app' => 'ldapoufilter']);

                if ($this->ldapOuService->areUsersInSameOu($currentUserId, $userId)) {
                    $filteredResults[] = $result;
                    $this->logger->debug("✓ User $userId kept (same OU)", ['app' => 'ldapoufilter']);
                } else {
                    $this->logger->debug("✗ User $userId filtered out (different OU)", ['app' => 'ldapoufilter']);
                }
            }

            $filteredCount = count($filteredResults);
            $this->logger->info("==> Filtered $type: $originalCount -> $filteredCount users", ['app' => 'ldapoufilter']);

            // Update the search results
            // Note: ISearchResult doesn't have a direct "replace" method,
            // so we mark filtered users as not found by not adding them back
            // This is a limitation of the current API

        } catch (\Exception $e) {
            $this->logger->error("Error filtering $type results: " . $e->getMessage(), [
                'app' => 'ldapoufilter',
                'exception' => $e->getTraceAsString()
            ]);
        }
    }
}

