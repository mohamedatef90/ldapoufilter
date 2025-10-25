<?php
declare(strict_types=1);

namespace OCA\LdapOuFilter\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCA\LdapOuFilter\Listener\UserSearchListener;
use OCA\LdapOuFilter\Service\LdapOuService;
use OCA\LdapOuFilter\Collaboration\OuFilterPlugin;
use OCP\Collaboration\Collaborators\SearchResultEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Collaboration\Collaborators\ISearchPlugin;

class Application extends App implements IBootstrap {
    public const APP_ID = 'ldapoufilter';
    
    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }
    
    public function register(IRegistrationContext $context): void {
        // Register services properly for Nextcloud 31
        // Note: No type hint on $c - Nextcloud passes DIContainer, not IServerContainer
        $context->registerService(LdapOuService::class, function($c) {
            return new LdapOuService(
                $c->get(\OCP\IUserManager::class),
                $c->get(\OCP\IConfig::class),
                $c->get(\Psr\Log\LoggerInterface::class)
            );
        });
        
        // Register UserSearchListener as a service with dependency injection
        $context->registerService(UserSearchListener::class, function($c) {
            return new UserSearchListener(
                $c->get(LdapOuService::class),
                $c->get(\OCP\IUserSession::class),
                $c->get(\Psr\Log\LoggerInterface::class)
            );
        });
        
        // Register OuFilterPlugin for Collaborator Search (sharees API)
        $context->registerService(OuFilterPlugin::class, function($c) {
            return new OuFilterPlugin(
                $c->get(LdapOuService::class),
                $c->get(\OCP\IUserSession::class),
                $c->get(\Psr\Log\LoggerInterface::class)
            );
        });
        
        // Register event listener for search results (for other search types)
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
        
        // CRITICAL: Register OuFilterPlugin with Collaborators Manager
        // This is what makes filtering work for the sharees API (file sharing)
        try {
            $collaboratorsManager = $server->get(\OCP\Collaboration\Collaborators\ISearch::class);
            $ouFilterPlugin = $server->get(OuFilterPlugin::class);
            
            // Register as a user search plugin with high priority
            $collaboratorsManager->registerPlugin([
                'shareType' => 'SHARE_TYPE_USER',
                'class' => OuFilterPlugin::class
            ]);
            
            $logger->info('✓ OU Filter Plugin registered with Collaborators Manager', ['app' => self::APP_ID]);
        } catch (\Exception $e) {
            $logger->error('Failed to register OU Filter Plugin: ' . $e->getMessage(), [
                'app' => self::APP_ID,
                'exception' => $e->getTraceAsString()
            ]);
        }
        
        // ADDITIONAL: Register event listener directly via Event Dispatcher
        // This ensures the listener is registered for other search contexts
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