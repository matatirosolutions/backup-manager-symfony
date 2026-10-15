<?php

namespace BM\BackupManagerBundle\DependencyInjection;

use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

class BMBackupManagerExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
        $config['storage'] = isset($config['storage']) ? $config['storage'] : [];
        $config['database'] = isset($config['database']) ? $config['database'] : [];
        $this->validateStorage($config['storage']);

        $managerIdMap = [
            'Local' => 'backup_manager.filesystems.local_filesystem',
            'AwsS3' => 'backup_manager.filesystems.awss3_filesystem',
        ];

        $filesystemDef = $container->getDefinition('backup_manager.filesystems');
        foreach ($config['storage'] as $storageKey => $storageConfig) {
            $filesystemDef->addMethodCall('add', [new Reference($managerIdMap[$storageConfig['type']])]);
        }

        $container->getDefinition('backup_manager.config_storage')
            ->replaceArgument(0, $config['storage']);

        $container->getDefinition('backup_manager.config_database')
            ->replaceArgument(0, $config['database']);

        if (isset($config['output_file_prefix'])) {
            $container->getDefinition('backup_manager.command.backup')
                ->replaceArgument(1, $config['output_file_prefix']);
        }
    }

    /**
     * We want to make sure the correct dependencies are installed for a storage.
     * @param array $config
     */
    private function validateStorage(array $config)
    {
        $requirements = [
            'Local' => ['package'=>'league/flysystem:^3.0', 'test'=>LocalFilesystemAdapter::class],
            'AwsS3' => ['package'=>'league/flysystem-aws-s3-v3:^3.0', 'test'=>AwsS3V3Adapter::class],
        ];

        foreach ($config as $key => $storageConfig) {
            $type = $storageConfig['type'];
            if (!class_exists($requirements[$type]['test'])) {
                throw new \LogicException(sprintf('To use the configuration key "%s" in "bm_backup_manager.storage.%s.type" you need to install "%s"', $type, $key, $requirements[$type]['package']));
            }
        }
    }
}
