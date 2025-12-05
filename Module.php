<?php
namespace ArchivesspaceConnector;

use Omeka\Module\AbstractModule;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Composer\Semver\Comparator;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl->allow(
            null,
            ['ArchivesspaceConnector\Api\Adapter\ArchivesspaceItemAdapter'],
            ['search', 'read']
            );
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $connection->exec("CREATE TABLE archivesspace_item (id INT AUTO_INCREMENT NOT NULL, item_id INT NOT NULL, job_id INT NOT NULL, aspace_api_url VARCHAR(255) NOT NULL, aspace_target_path VARCHAR(255) NOT NULL, last_modified DATETIME NOT NULL, UNIQUE INDEX UNIQ_3A789461126F525E (item_id), INDEX IDX_3A789461BE04EA9 (job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;");
        $connection->exec("CREATE TABLE archivesspace_item_set (id INT AUTO_INCREMENT NOT NULL, item_set_id INT NOT NULL, job_id INT NOT NULL, aspace_api_url VARCHAR(255) NOT NULL, aspace_target_path VARCHAR(255) NOT NULL, last_modified DATETIME NOT NULL, UNIQUE INDEX UNIQ_F3159620960278D7 (item_set_id), INDEX IDX_F3159620BE04EA9 (job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;");
        $connection->exec("CREATE TABLE archivesspace_import (id INT AUTO_INCREMENT NOT NULL, job_id INT NOT NULL, undo_job_id INT DEFAULT NULL, rerun_job_id INT DEFAULT NULL, added_count INT NOT NULL, updated_count INT NOT NULL, comment LONGTEXT DEFAULT NULL, hierarchy_id INT NOT NULL, UNIQUE INDEX UNIQ_898F0D5DBE04EA9 (job_id), UNIQUE INDEX UNIQ_898F0D5D4C276F75 (undo_job_id), UNIQUE INDEX UNIQ_898F0D5D7071F49C (rerun_job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;");
        $connection->exec("ALTER TABLE archivesspace_item ADD CONSTRAINT FK_3A789461126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE;");
        $connection->exec("ALTER TABLE archivesspace_item ADD CONSTRAINT FK_3A789461BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id);");
        $connection->exec("ALTER TABLE archivesspace_item_set ADD CONSTRAINT FK_F3159620960278D7 FOREIGN KEY (item_set_id) REFERENCES item_set (id) ON DELETE CASCADE;");
        $connection->exec("ALTER TABLE archivesspace_item_set ADD CONSTRAINT FK_F3159620BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id);");
        $connection->exec("ALTER TABLE archivesspace_import ADD CONSTRAINT FK_898F0D5DBE04EA9 FOREIGN KEY (job_id) REFERENCES job (id);");
        $connection->exec("ALTER TABLE archivesspace_import ADD CONSTRAINT FK_898F0D5D4C276F75 FOREIGN KEY (undo_job_id) REFERENCES job (id);");
        $connection->exec("ALTER TABLE archivesspace_import ADD CONSTRAINT FK_898F0D5D7071F49C FOREIGN KEY (rerun_job_id) REFERENCES job (id);");
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $connection->exec("ALTER TABLE archivesspace_item DROP FOREIGN KEY FK_3A789461126F525E;");
        $connection->exec("ALTER TABLE archivesspace_item DROP FOREIGN KEY FK_3A789461BE04EA9;");
        $connection->exec("ALTER TABLE archivesspace_item_set DROP FOREIGN KEY FK_F3159620960278D7;");
        $connection->exec("ALTER TABLE archivesspace_item_set DROP FOREIGN KEY FK_F3159620BE04EA9;");
        $connection->exec("ALTER TABLE archivesspace_import DROP FOREIGN KEY FK_898F0D5DBE04EA9;");
        $connection->exec("ALTER TABLE archivesspace_import DROP FOREIGN KEY FK_898F0D5D4C276F75;");
        $connection->exec("ALTER TABLE archivesspace_import DROP FOREIGN KEY FK_898F0D5D7071F49C;");
        $connection->exec('DROP TABLE archivesspace_item');
        $connection->exec('DROP TABLE archivesspace_item_set');
        $connection->exec('DROP TABLE archivesspace_import');
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.after',
            [$this, 'showSource']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.show.after',
            [$this, 'showSource']
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.search.query',
            [$this, 'importSearch']
        );
    }

    public function showSource($event)
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $view = $event->getTarget();
        $resource = $view->resource;
        switch (get_class($resource)) {
            case 'Omeka\Api\Representation\ItemRepresentation':
                $response = $api->search('archivesspace_items', ['item_id' => $resource->id()]);
                $archivesspaceResources = $response->getContent();
                if (!empty($archivesspaceResource)) {
                    $archivesspaceResource = $archivesspaceResources[0];
                    $resourceTitle = $archivesspaceResource->item()->title() ?: $view->translate('link');
                }
                break;
            case 'Omeka\Api\Representation\ItemSetRepresentation':
                $response = $api->search('archivesspace_item_sets', ['item_set_id' => $resource->id()]);
                $archivesspaceResources = $response->getContent();
                if (!empty($archivesspaceResource)) {
                    $archivesspaceResource = $archivesspaceResources[0];
                    $resourceTitle = $archivesspaceResource->itemSet()->title() ?: $view->translate('link');
                }
                break;
            default:
                return;
        }
        
        if (!empty($archivesspaceResource)) {
            $targetPath = trim($archivesspaceResource->targetPath(), '/');
            $parsedUrl = parse_url($archivesspaceResource->apiUrl());
            $resourceLink = sprintf('%s://%s/%s', $parsedUrl['scheme'], $parsedUrl['host'], $targetPath);
            echo '<h3>' . $view->translate('Original') . '</h3>';
            echo '<p><a href="' . $resourceLink . '" target="_blank">' . $resourceTitle . '</a></p>';
            echo '<p>' . $view->translate('Last Modified') . ' ' . $view->i18n()->dateFormat($archivesspaceResource->lastModified()) . '</p>';
        }
    }

    public function importSearch($event)
    {
        $query = $event->getParam('request')->getContent();
        if (isset($query['archivesspace_import_id'])) {
            $qb = $event->getParam('queryBuilder');
            $adapter = $event->getTarget();
            $importItemAlias = $adapter->createAlias();
            $qb->innerJoin(
                \ArchivesspaceConnector\Entity\ArchivesspaceItem::class, $importItemAlias,
                'WITH', "$importItemAlias.item = omeka_root.id"
            )->andWhere($qb->expr()->eq(
                "$importItemAlias.job",
                $adapter->createNamedParameter($qb, $query['archivesspace_import_id'])
            ));
        }
    }
}
