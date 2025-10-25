<?php
namespace OCA\LdapOuFilter\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Collaboration\Collaborators\SearchResultEvent;
use OCA\LdapOuFilter\Service\LdapOuService;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class UserSearchListener implements IEventListener {
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
    
    public function handle(Event $event): void {
        $this->logger->info('UserSearchListener::handle called with event: ' . get_class($event), ['app' => 'ldapoufilter']);
        
        if (!($event instanceof SearchResultEvent)) {
            $this->logger->warning('Event is not SearchResultEvent, it is: ' . get_class($event), ['app' => 'ldapoufilter']);
            return;
        }
        
        $this->logger->info('UserSearchListener triggered - SearchResultEvent confirmed!', ['app' => 'ldapoufilter']);
        
        $currentUser = $this->userSession->getUser();
        if (!$currentUser) {
            $this->logger->warning('No current user in session', ['app' => 'ldapoufilter']);
            return;
        }
        
        $currentUserId = $currentUser->getUID();
        $this->logger->info("Starting to filter search results for user: $currentUserId", ['app' => 'ldapoufilter']);
        
        try {
            $searchResult = $event->getSearchResult();
            $this->logger->info('Got search result object', ['app' => 'ldapoufilter']);
            
            // Filter users in search results
            if ($searchResult->hasResult('users')) {
                $users = $searchResult->getResult('users');
                $this->logger->info("Original users count: " . count($users['results'] ?? []), ['app' => 'ldapoufilter']);
                
                $filteredUsers = $this->filterSearchResults($users, $currentUserId);
                
                // Update the search results with filtered users
                $searchResult->unsetResult('users');
                $searchResult->addResultSet('users', $filteredUsers['results'], $filteredUsers['exact']);
                
                $this->logger->info("Filtered users count: " . count($filteredUsers['results']), ['app' => 'ldapoufilter']);
            } else {
                $this->logger->info('No users in search results', ['app' => 'ldapoufilter']);
            }
            
            // Also filter remotes if they exist
            if ($searchResult->hasResult('remotes')) {
                $remotes = $searchResult->getResult('remotes');
                $filteredRemotes = $this->filterSearchResults($remotes, $currentUserId);
                
                $searchResult->unsetResult('remotes');
                $searchResult->addResultSet('remotes', $filteredRemotes['results'], $filteredRemotes['exact']);
                
                $this->logger->info('Filtered remotes', ['app' => 'ldapoufilter']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error filtering search results: ' . $e->getMessage(), [
                'app' => 'ldapoufilter',
                'exception' => $e->getTraceAsString()
            ]);
        }
    }
    
    private function filterSearchResults(array $results, string $currentUserId): array {
        $filtered = [
            'results' => [],
            'exact' => []
        ];
        
        // Filter regular results
        if (isset($results['results']) && is_array($results['results'])) {
            $originalCount = count($results['results']);
            $filtered['results'] = $this->ldapOuService->filterUsersByOu(
                $results['results'],
                $currentUserId
            );
            $filteredCount = count($filtered['results']);
            
            $this->logger->info("Filtered search results: $originalCount -> $filteredCount users");
        }
        
        // Filter exact matches
        if (isset($results['exact']) && is_array($results['exact'])) {
            $filtered['exact'] = $this->ldapOuService->filterUsersByOu(
                $results['exact'],
                $currentUserId
            );
        }
        
        // Keep other properties if they exist
        foreach ($results as $key => $value) {
            if ($key !== 'results' && $key !== 'exact') {
                $filtered[$key] = $value;
            }
        }
        
        return $filtered;
    }
}