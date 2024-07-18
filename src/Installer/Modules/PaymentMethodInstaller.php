<?php


namespace Go2FlowHeidiPayPayment\Installer\Modules;

use Go2FlowHeidiPayPayment\Go2FlowHeidiPayPayment;
use Go2FlowHeidiPayPayment\Installer\InstallerInterface;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Context;
use Go2FlowHeidiPayPayment\Handler\PaymentHandler;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class PaymentMethodInstaller implements InstallerInterface
{

    /**
     * @var string
     */
    public const HEIDIPAY_METHOD = 'heidipay';
    public const HEIDIPAY_CREDIT_METHOD = 'heidipay_credit';

    const PAYMENT_METHODS = [
        self::HEIDIPAY_METHOD => [
            'icon' => 'heidipay-checkout-credit',
            'translations' => [
                'de-DE' => [
                    'name' => 'HeidiPay - Ratenzahlung (0% Zinsen) ',
                ],
                'en-GB' => [
                    'name' => 'HeidiPay - Ratepay (0%)',
                ],
            ],
        ],
        self::HEIDIPAY_CREDIT_METHOD => [
            'icon' => 'heidipay-checkout-credit',
            'translations' => [
                'de-DE' => [
                    'name' => 'HeidiPay - Kredit',
                ],
                'en-GB' => [
                    'name' => 'HeidiPay - Credit',
                ],
            ],
        ],
    ];

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
    ) {
        $this->mediaRepository = $mediaRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->pluginIdProvider = $pluginIdProvider;
        $this->fileSaver = $fileSaver;
        $this->mediaFolderRepository = $mediaFolderRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function install(InstallContext $context): void
    {
        // Nothing to do
    }

    public function uninstall(UninstallContext $context): void
    {
        // Nothing to do, payment methods already deactivated
    }

    public function activate(ActivateContext $context): void
    {
        foreach (self::PAYMENT_METHODS as $heidiPayPaymentMethodIdentifier => $heidiPayPaymentMethod) {
            $this->upsertPaymentMethod($context->getContext(), $heidiPayPaymentMethod, $heidiPayPaymentMethodIdentifier);
        }

        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', PaymentHandler::class));
        $paymentMethods = $this->paymentMethodRepository->searchIds($paymentCriteria, Context::createDefaultContext());

        foreach ($paymentMethods->getIds() as $paymentMethodId) {
            $this->setPaymentMethodIsActive($context->getContext(), $paymentMethodId, true);
        }
    }

    public function deactivate(DeactivateContext $context): void
    {
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', PaymentHandler::class));
        $paymentMethods = $this->paymentMethodRepository->searchIds($paymentCriteria, Context::createDefaultContext());

        foreach ($paymentMethods->getIds() as $paymentMethodId) {
            $this->setPaymentMethodIsActive($context->getContext(), $paymentMethodId, false);
        }
    }

    public function update(UpdateContext $context): void {
        foreach (self::PAYMENT_METHODS as $heidiPayPaymentMethodIdentifier => $heidiPayPaymentMethod) {
            $this->upsertPaymentMethod($context->getContext(), $heidiPayPaymentMethod, $heidiPayPaymentMethodIdentifier);
        }
    }

    /**
     * @param Context $context
     * @param array $heidiPayPaymentMethod
     * @param string $heidiPayPaymentMethodIdentifier
     * @return void
     */
    private function upsertPaymentMethod(Context $context, array $heidiPayPaymentMethod, string $heidiPayPaymentMethodIdentifier): void
    {
        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass(Go2FlowHeidiPayPayment::class, $context);

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('handlerIdentifier', PaymentHandler::class))
            ->addFilter(new EqualsFilter('customFields.heidipay_payment_method_name', PaymentHandler::PAYMENT_METHOD_PREFIX . $heidiPayPaymentMethodIdentifier))
            ->setLimit(1);
        $paymentMethods = $this->paymentMethodRepository->search($criteria, Context::createDefaultContext());

        $paymentMethodId = null;
        $paymentMethodActive = false;

        if ($paymentMethods->count()){
            $paymentMethod = $paymentMethods->getEntities()->first();
            $paymentMethodId = $paymentMethod->getId();
            $paymentMethodActive = $paymentMethod->getActive();
        }

        // Upload icon to the media repository
        $mediaId = $this->getMediaId($heidiPayPaymentMethod['icon'], $context);

        $options = [
            'id' => $paymentMethodId,
            'handlerIdentifier' => PaymentHandler::class,
            'name' => $heidiPayPaymentMethod['translations']['en-GB']['name'],
            'translations' => $heidiPayPaymentMethod['translations'],
            'active' => $paymentMethodActive,
            'pluginId' => $pluginId,
            'mediaId' => $mediaId,
            'technicalName' => PaymentHandler::PAYMENT_METHOD_PREFIX . $heidiPayPaymentMethodIdentifier,
            'customFields' => [
                'heidipay_payment_method_name' => PaymentHandler::PAYMENT_METHOD_PREFIX . $heidiPayPaymentMethodIdentifier,
            ]
        ];

        if (!$paymentMethodId) {
            $options['afterOrderEnabled'] = true;
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($options): void {
            $this->paymentMethodRepository->upsert([$options], $context);
        });
    }

    /**
     * @param Context $context
     * @param int $paymentMethodId
     * @param boolean $active
     * @return void
     */
    private function setPaymentMethodIsActive($context, $paymentMethodId, $active) {
        $paymentMethod = [
            'id' => $paymentMethodId,
            'active' => $active,
        ];
        $this->paymentMethodRepository->update([$paymentMethod], $context);
    }

    /**
     * Retrieve the icon from the database, or add it.
     *
     * @param array<mixed> $paymentMethod
     * @param Context $context
     *
     * @return string
     */
    private function getMediaId(string $paymentMethod, Context $context): string
    {

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $paymentMethod));

        /** @var MediaCollection $icons */
        $icons = $this->mediaRepository->search($criteria, $context);
        if ($icons->count() && $icons->first() !== null) {
            return $icons->first()->getId();
        }

        // Add icon to the media library
        $iconMime = 'image/svg+xml';
        $iconExt = 'svg';
        $iconBlob = (string)file_get_contents(Go2FlowHeidiPayPayment::CREDIT_CHECKOUT_IMAGE);

        return $this->saveFile(
            $iconBlob,
            $iconExt,
            $iconMime,
            $paymentMethod,
            $context,
            'HeidiPay Payments - Icons',
            null,
            false
        );
    }

    public function saveFile(
        string $blob,
        string $extension,
        string $contentType,
        string $filename,
        Context $context,
        ?string $folder = null,
        ?string $mediaId = null,
        bool $private = true
    ): string {
        $mediaFile = $this->fetchBlob($blob, $extension, $contentType);

        if (!$mediaId) {
            $mediaId = $this->createMediaInFolder($folder ?? '', $context, $private);
        }

        $this->fileSaver->persistFileToMedia($mediaFile, $filename, $mediaId, $context);

        return $mediaId;
    }

    public function createMediaInFolder(string $folder, Context $context, bool $private = true): string
    {
        $mediaId = Uuid::randomHex();
        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'private' => $private,
                    'mediaFolderId' => $this->getMediaDefaultFolderId($folder, $context),
                ],
            ],
            $context
        );

        return $mediaId;
    }

    private function getMediaDefaultFolderId(string $folder, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', $folder));
        $criteria->addAssociation('defaultFolder');
        $criteria->setLimit(1);
        $defaultFolder = $this->mediaFolderRepository->search($criteria, $context);
        $defaultFolderId = null;
        if ($defaultFolder->count() === 1) {
            $defaultFolderId = $defaultFolder->first()->getId();
        }

        return $defaultFolderId;
    }

    private function fetchBlob(string $blob, string $extension, string $contentType): MediaFile
    {
        $tempFile = (string) tempnam(sys_get_temp_dir(), '');
        $fh = @fopen($tempFile, 'wb');
        \assert($fh !== false);

        $blobSize = (int) @fwrite($fh, $blob);
        $fileHash = $tempFile ? hash_file('md5', $tempFile) : null;

        return new MediaFile(
            $tempFile,
            $contentType,
            $extension,
            $blobSize,
            $fileHash ?: null
        );
    }
}
