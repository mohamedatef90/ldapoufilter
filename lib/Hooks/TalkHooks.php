<?php
namespace OCA\LdapOuFilter\Hooks;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCA\LdapOuFilter\Service\LdapOuService;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Hook for Talk app mentions
 */
class TalkHooks implements IEventListener {
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
        // Check if this is a Talk search event
        $className = get_class($event);
        if (strpos($className, 'Talk') === false) {
            return;
        }
        
        $currentUser = $this->userSession->getUser();
        if (!$currentUser) {
            return;
        }
        
        $currentUserId = $currentUser->getUID();
        
        // Try to get and filter search results
        try {
            if (method_exists($event, 'getSearchResults')) {
                $searchResults = $event->getSearchResults();
                
                // Filter results based on OU
                $filteredResults = [];
                foreach ($searchResults as $result) {
                    if (isset($result['id'])) {
                        if ($this->ldapOuService->areUsersInSameOu($currentUserId, $result['id'])) {
                            $filteredResults[] = $result;
                        }
                    }
                }
                
                // Set filtered results back if method exists
                if (method_exists($event, 'setSearchResults')) {
                    $event->setSearchResults($filteredResults);
                }
                
                $this->logger->debug('Filtered Talk search results', [
                    'original_count' => count($searchResults),
                    'filtered_count' => count($filteredResults),
                    'user' => $currentUserId
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error filtering Talk results', [
                'exception' => $e->getMessage()
            ]);
        }
    }
}