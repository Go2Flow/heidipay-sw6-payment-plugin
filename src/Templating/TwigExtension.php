<?php

namespace Go2FlowHeidiPayPayment\Templating;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    /**
     * @var SystemConfigService
     */
    protected SystemConfigService $configService;

    public function __construct(SystemConfigService $configService) {
        $this->configService = $configService;
    }

    /**
     * @return array
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('heidi_best_term', [$this, 'heidiBestTerm']),
            new TwigFunction('heidi_add_fee', [$this, 'heidiAddFee']),
        ];
    }

    public function heidiBestTerm($price)
    {
        $terms = $this->configService->get('Go2FlowHeidiPayPayment.settings.heidiPromotionTerms', null);
        $minInstalment = $this->configService->get('Go2FlowHeidiPayPayment.settings.heidiPromotionWidgetMinInstalment', null);


        if(!is_array($terms)) {
            return false;
        }

        if(empty($minInstalment)) {
            $minInstalment = 0;
        }

        $minInstalment = $minInstalment * 100;

        rsort($terms);

        foreach ($terms as $term) {
            $instalmentPrice = $price / $term;
            if($instalmentPrice >= $minInstalment) {
                return $term;
            }
        }
        return false;
    }

    public function heidiAddFee($minorAmount)
    {
        $fee = $this->configService->get('Go2FlowHeidiPayPayment.settings.heidiPromotionWidgetFee', null);
        $fee = (!empty($fee) ? (float) str_replace(',', '.', $fee) : 0);
        if ($fee > 0) {
            $minorAmount = round($minorAmount + ($minorAmount * ($fee / 100)));
        }
        return $minorAmount;
    }

    public function getAvailableTerms($terms, $minorAmount, $minimumInstalmentPrice)
    {
        $availableTerms = [];

        $terms = array_map('intval', $terms);
        rsort($terms, SORT_NUMERIC);
        foreach ($terms as $term) {
            $installPrice = $minorAmount / $term;
            if ($installPrice >= $minimumInstalmentPrice) {
                $availableTerms[] = $term;
            }
        }
        return $availableTerms;
    }
}
