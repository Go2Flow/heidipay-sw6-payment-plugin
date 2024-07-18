<?php

namespace Go2FlowHeidiPayPayment\Installer;

use Go2FlowHeidiPayPayment\Installer\Modules\PaymentMethodInstaller;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

class HeidiPayPaymentInstaller implements InstallerInterface
{

    /** @var EntityRepository */
    private EntityRepository $paymentMethodRepository;

    /** @var PluginIdProvider */
    private PluginIdProvider $pluginIdProvider;

    /** @var EntityRepository */
    private EntityRepository $mediaRepository;
    private FileSaver $fileSaver;
    private EntityRepository $mediaFolderRepository;

    public function __construct(
        EntityRepository $mediaRepository,
        EntityRepository $mediaFolderRepository,
        EntityRepository $paymentMethodRepository,
        PluginIdProvider          $pluginIdProvider,
        FileSaver $fileSaver,
    )
    {
        $this->mediaRepository = $mediaRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->pluginIdProvider = $pluginIdProvider;
        $this->fileSaver = $fileSaver;
        $this->mediaFolderRepository = $mediaFolderRepository;
    }

    public function install(InstallContext $context): void
    {
        $this->getPaymentMethodInstaller()->install($context);
    }

    public function update(UpdateContext $context): void
    {
        $this->getPaymentMethodInstaller()->update($context);
    }

    public function uninstall(UninstallContext $context): void
    {
        $this->getPaymentMethodInstaller()->uninstall($context);
    }

    public function activate(ActivateContext $context): void
    {
        $this->getPaymentMethodInstaller()->activate($context);
    }

    public function deactivate(DeactivateContext $context): void
    {
        $this->getPaymentMethodInstaller()->deactivate($context);
    }

    private function getPaymentMethodInstaller(): PaymentMethodInstaller
    {

        return new PaymentMethodInstaller(
            $this->mediaRepository,
            $this->mediaFolderRepository,
            $this->paymentMethodRepository,
            $this->pluginIdProvider,
            $this->fileSaver,
        );
    }
}
