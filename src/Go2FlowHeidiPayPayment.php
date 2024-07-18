<?php declare(strict_types=1);

namespace Go2FlowHeidiPayPayment;

use Doctrine\DBAL\Connection;
use Go2FlowHeidiPayPayment\Core\Content\WebhookTokens\WebhookTokenDefinition;
use Go2FlowHeidiPayPayment\Installer\HeidiPayPaymentInstaller;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileLoader;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class Go2FlowHeidiPayPayment extends Plugin
{

    const HEIDIPAY_CURRENT_VERSION = '1.4.0';
    const CREDIT_CHECKOUT_IMAGE = 'https://storage.googleapis.com/heidi-public-images/heidipay_upstream_inline_logos/heidipay_cards_qr.svg';

    /**
     * @param ContainerBuilder $container
     * @return void
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $locator = new FileLocator('Resources/config');

        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
        ]);

        $configLoader = new DelegatingLoader($resolver);

        $confDir = \rtrim($this->getPath(), '/') . '/Resources/config';

        $configLoader->load($confDir . '/{packages}/*.yaml', 'glob');
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * @param InstallContext $installContext
     */
    public function install(InstallContext $installContext): void
    {
        //get the config service
        $config = $this->container->get('Shopware\Core\System\SystemConfig\SystemConfigService');

        //set the specified values as defaults
        // This is the standard Sandbox API Key from Heidipay which is public, so no need to worry
        $config->set('Go2FlowHeidiPayPayment.settings.heidiSecretKey', "5f6b49230c80f8b894e27b528a063acdac887ceb");
        $config->set('Go2FlowHeidiPayPayment.settings.heidiMode', "0");
        $config->set('Go2FlowHeidiPayPayment.settings.heidiPromotionShowOnProduct', "1");
        $config->set('Go2FlowHeidiPayPayment.settings.heidiPromotionShowOnCart', "1");
        $config->set('Go2FlowHeidiPayPayment.settings.heidiPromotionWidgetFee', 0);
        $config->set('Go2FlowHeidiPayPayment.settings.heidiPromotionProductMode', "0");
        $config->set('Go2FlowHeidiPayPayment.settings.heidiPromotionWidgetMinAmount', 0);
        $config->set('Go2FlowHeidiPayPayment.settings.heidiPromotionWidgetMinInstalment', 1);
        $config->set('Go2FlowHeidiPayPayment.settings.heidiPromotionTerms', ["3", "6"]);
        $config->set('Go2FlowHeidiPayPayment.settings.heidiPromotionTermsCredit', ["12"]);
        $config->set('Go2FlowHeidiPayPayment.settings.heidiPromotionPublicApiKey', "-");
        $config->set('Go2FlowHeidiPayPayment.settings.heidiMaximumOrderValue', 1000);
        $this->getInstaller()->install($installContext);
        parent::install($installContext);
    }

    /**
     * @param UninstallContext $uninstallContext
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        $this->getInstaller()->uninstall($uninstallContext);
        if (!$uninstallContext->keepUserData()) {
            $this->dropTables();
        }
        parent::uninstall($uninstallContext);
    }

    private function dropTables(): void
    {
        $connection = $this->container->get(Connection::class);
        $connection->executeStatement(\sprintf('DROP TABLE IF EXISTS `%s`', WebhookTokenDefinition::ENTITY_NAME));
    }

    /**
     * @param ActivateContext $activateContext
     */
    public function activate(ActivateContext $activateContext): void
    {
        $this->getInstaller()->activate($activateContext);
        parent::activate($activateContext);
    }

    /**
     * @param DeactivateContext $deactivateContext
     */
    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->getInstaller()->deactivate($deactivateContext);
        parent::deactivate($deactivateContext);
    }

    /**
     * @param UpdateContext $updateContext
     */
    public function update(UpdateContext $updateContext): void
    {
        /** @var SystemConfigService $config */
        $config = $this->container->get(SystemConfigService::class);
        if (\version_compare($updateContext->getCurrentPluginVersion(), '1.2.0', '<')) {
            $config->set('Go2FlowHeidiPayPayment.settings.heidiPromotionProductMode', "0");
        }
        if (\version_compare($updateContext->getCurrentPluginVersion(), '1.3.0', '<')) {
            $config->set('Go2FlowHeidiPayPayment.settings.heidiPromotionTermsCredit', ["12"]);
        }
        $this->getInstaller()->update($updateContext);
        parent::update($updateContext);
    }

    private function getInstaller(): HeidiPayPaymentInstaller
    {
        return new HeidiPayPaymentInstaller(
            $this->container->get('media.repository'),
            $this->container->get('media_folder.repository'),
            $this->container->get('payment_method.repository'),
            $this->container->get('Shopware\Core\Framework\Plugin\Util\PluginIdProvider'),
            $this->container->get(FileSaver::class),
        );
    }
}
