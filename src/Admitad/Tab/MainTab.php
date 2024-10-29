<?php

namespace Admitad\Tab;

use Admitad\AdmitadContainer;

class MainTab extends Tab
{
    protected string $name = 'main';

    public function __construct(protected AdmitadContainer $container) {}

    public function getSettings(): array
    {
        $settings = $this->container->getSettings();
        $defaultCampaignCode = '';
        $defaultPostbackKey = '';
        $defaultCurrencyCode = 'RUB';

        if (!$settings->get('main', 'campaign_code') || !$settings->get('main', 'postback_key')) {
            if ($data = $this->container->getAdmitadManager()->getAdvertiserInfo()) {
                $defaultCampaignCode = $data['campaign_code'];
                $defaultPostbackKey = $data['postback_key'];
            }
        }

        return [
            'campaign_code' => [
                'type' => 'text',
                'label' => __('Campaign code', 'admitadtracking'),
                'default' => $defaultCampaignCode,
            ],
            'postback_key' => [
                'type' => 'text',
                'label' => __('Postback key', 'admitadtracking'),
                'default' => $defaultPostbackKey,
            ],
            'currency_code' => [
                'type' => 'text',
                'label' => __('Currency code', 'admitadtracking'),
                'default' => $defaultCurrencyCode,
            ],
        ];
    }

    protected function getDefaults(): array
    {
        return [
            'campaign_code' => '',
            'postback_key' => '',
            'currency_code' => 'RUB',
        ];
    }
}
