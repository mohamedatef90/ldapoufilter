<?php
declare(strict_types=1);

namespace OCA\LdapOuFilter\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCA\LdapOuFilter\Listener\UserSearchListener;
use OCA\LdapOuFilter\Service\LdapOuService;
use OCP\Collaboration\Collaborators\SearchResultEvent;
use OCP\IServerContainer;
use OCP\EventDispatcher\IEventDispatcher;

class Application extends App implements IBootstrap {
    public const APP_ID = 'ldapoufilter';
    
    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }
    
    public function register(IRegistrationContext $context): void {
        // Register services properly for Nextcloud 31
        $context->registerService(LdapOuService::class, function(IServerContainer $c) {
            return new LdapOuService(
                $c->get(\OCP\IUserManager::class),
                $c->get(\OCP\IConfig::class),
                $c->get(\Psr\Log\LoggerInterface::class)
            );
        });
        
        // Register UserSearchListener as a service with dependency injection
        $context->registerService(UserSearchListener::class, function(IServerContainer $c) {
            return new UserSearchListener(
                $c->get(LdapOuService::class),
                $c->get(\OCP\IUserSession::class),
                $c->get(\Psr\Log\LoggerInterface::class)
            );
        });
        
        // Register event listener for search results
        $context->registerEventListener(
            SearchResultEvent::class,
            UserSearchListener::class
        );
    }
    
    public function boot(IBootContext $context): void {
        $server = $context->getServerContainer();
        
        // Log that the app has booted successfully
        $logger = $server->get(\Psr\Log\LoggerInterface::class);
        $logger->info('LDAP OU Filter app booted successfully', ['app' => self::APP_ID]);
        
        // ADDITIONAL: Register event listener directly via Event Dispatcher
        // This ensures the listener is registered even if the Bootstrap registration fails
        try {
            $dispatcher = $server->get(IEventDispatcher::class);
            $listener = $server->get(UserSearchListener::class);
            
            // Register with high priority (early execution)
            $dispatcher->addListener(SearchResultEvent::class, function($event) use ($listener, $logger) {
                $logger->info('SearchResultEvent detected! Calling listener...', ['app' => self::APP_ID]);
                $listener->handle($event);
            }, 100);
            
            $logger->info('Event listener registered directly via dispatcher', ['app' => self::APP_ID]);
        } catch (\Exception $e) {
            $logger->error('Failed to register event listener: ' . $e->getMessage(), ['app' => self::APP_ID]);
        }
    }
}