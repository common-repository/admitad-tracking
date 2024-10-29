<?php

namespace Admitad\Tab;

use Admitad\AdmitadContainer;

class AuthTab extends Tab
{
    protected string $name = 'auth';

    public function __construct(protected AdmitadContainer $container) {}

    public function handle($options): array
    {
        $api = $this->container->getApi();

        $api->selfAuthorize(
            $options['client_id'],
            $options['client_secret'],
            'advertiser_info'
        );

        if (!$api->isAuthorized()) {
            $options['client_id'] = $options['client_secret'] = '';

            return $options;
        }

        $data = $this->container->getAdmitadManager()->getAdvertiserInfo();

        if (empty($data['campaign_code']) || empty($data['postback_key'])) {
            $options['client_id'] = $options['client_secret'] = '';

            return $options;
        }
        update_option('admitadtracking_main', [
            'campaign_code' => $data['campaign_code'],
            'postback_key' => $data['postback_key'],
        ]);

        return $options;
    }

    public function getSettings(): array
    {
        return [
            'client_id' => [
                'type' => 'text',
                'label' => __('Client Id', 'admitadtracking'),
            ],
            'client_secret' => [
                'type' => 'text',
                'label' => __('Client Secret', 'admitadtracking'),
            ],
        ];
    }

    protected function getDefaults(): array
    {
        return ['client_id' => '', 'client_secret' => ''];
    }
}
