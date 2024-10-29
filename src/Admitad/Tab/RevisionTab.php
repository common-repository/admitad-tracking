<?php

namespace Admitad\Tab;

use Admitad\AdmitadContainer;

class RevisionTab extends Tab
{
    protected string $name = 'revision';

    public function __construct(protected AdmitadContainer $container) {}

    public function init(): void
    {
        parent::init();
        add_settings_field('url', 'Url', [$this, 'getUrlView'], $this->getPageName(), $this->getSettingName());
    }

    public function getUrlView(): void
    {
        $name = $this->container->getSettings()->get($this->name, 'name');
        $url = $authUrl = get_home_url() . '/admitad/' . $name . '.xml';
        $parts = parse_url($url);

        if (isset($parts['scheme'], $parts['host'])) {
            $authUrl = $parts['scheme']
                . '://'
                . $this->container->getSettings()->get($this->name, 'login')
                . ':'
                . $this->container->getSettings()->get($this->name, 'password')
                . '@'
                . $parts['host']
                . $parts['path'];
        }

        ?>
        <a href="<?php echo $authUrl; ?>" target="_blank"><?php echo $url; ?></a>
        <?php
    }

    public function getSettings(): array
    {
        return [
            'name' => [
                'type' => 'text',
                'label' => __('File Name', 'admitadtracking'),
            ],
            'login' => [
                'type' => 'text',
                'label' => __('Login', 'admitadtracking'),
            ],
            'password' => [
                'type' => 'text',
                'label' => __('Password', 'admitadtracking'),
            ],
        ];
    }

    protected function getDefaults(): array
    {
        return ['name' => '', 'login' => '', 'password' => ''];
    }
}
