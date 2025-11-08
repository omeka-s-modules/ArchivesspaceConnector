<?php
namespace ArchivesspaceConnector\Service\Controller;

use ArchivesspaceConnector\Controller\IndexController;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $client = $serviceLocator->get('Omeka\HttpClient');
        $indexController = new IndexController($client);
        return $indexController;
    }
}
