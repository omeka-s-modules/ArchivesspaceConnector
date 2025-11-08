<?php
namespace ArchivesspaceConnector\Service\Form;

use ArchivesspaceConnector\Form\ImportForm;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class ImportFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new ImportForm(null, $options ?? []);
        $form->setAuthenticationService($services->get('Omeka\AuthenticationService'));
        $form->setUserSettings($services->get('Omeka\Settings\User'));
        $form->setUrlHelper($services->get('ViewHelperManager')->get('Url'));
        $form->setApiManager($services->get('Omeka\ApiManager'));
        $form->setModuleManager($services->get('Omeka\ModuleManager'));
        return $form;
    }
}
