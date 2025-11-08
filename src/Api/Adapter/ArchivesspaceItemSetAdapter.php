<?php
namespace ArchivesspaceConnector\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class ArchivesspaceItemSetAdapter extends AbstractEntityAdapter
{
    public function getEntityClass()
    {
        return 'ArchivesspaceConnector\Entity\ArchivesspaceItemSet';
    }

    public function getResourceName()
    {
        return 'archivesspace_itemset';
    }

    public function getRepresentationClass()
    {
        return 'ArchivesspaceConnector\Api\Representation\ArchivesspaceItemSetRepresentation';
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['aspace_api_url'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.aspaceApiUrl',
                $this->createNamedParameter($qb, $query['aspace_api_url']))
            );
        }

        if (isset($query['aspace_target_path'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.aspaceTargetPath',
                $this->createNamedParameter($qb, $query['aspace_target_path']))
            );
        }

        if (isset($query['job_id'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.job',
                $this->createNamedParameter($qb, $query['job_id']))
            );
        }

        if (isset($query['item_set_id'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.itemSet',
                $this->createNamedParameter($qb, $query['item_set_id'])
            ));
        }
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        $data = $request->getContent();
        if (isset($data['aspace_api_url'])) {
            $entity->setApiUrl($data['aspace_api_url']);
        }
        if (isset($data['aspace_target_path'])) {
            $entity->setTargetPath($data['aspace_target_path']);
        }
        if (isset($data['o:job']['o:id'])) {
            $job = $this->getAdapter('jobs')->findEntity($data['o:job']['o:id']);
            $entity->setJob($job);
        }
        if (isset($data['o:item_set']['o:id'])) {
            $itemSet = $this->getAdapter('item_sets')->findEntity($data['o:item_set']['o:id']);
            $entity->setItemSet($itemSet);
        }
        if (isset($data['last_modified'])) {
            $entity->setLastModified($data['last_modified']);
        }
    }
}
