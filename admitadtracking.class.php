<?php

declare(strict_types=1);

use Admitad\AdmitadContainer;
use Admitad\AdmitadRevision;
use Admitad\Tab\ActionsTab;
use Admitad\Tab\AuthTab;
use Admitad\Tab\MainTab;
use Admitad\Tab\PromoActionsTab;
use Admitad\Tab\RevisionTab;

class AdmitadTrackingPlugin
{
    protected $container;

    protected $prefix = 'admitadtracking-options';

    public function init()
    {
        $this->getContainer()->getAdmitadManager()->handleUser();
        $this->getContainer()->getAdmitadManager()->handleGclid();

        $this->registerScripts();

        new AdmitadRevision($this->getContainer());
    }

    public function onLogin($login)
    {
        $user = null;

        $uid = $this->getContainer()->getParameters()->getUserId();
        $gclid = $this->getContainer()->getParameters()->getGclid();

        if (function_exists('get_user_by')) {
            $user = get_user_by('login', $login);
        } else {
            if (function_exists('get_userdatabylogin')) {
                $user = get_userdatabylogin($login);
            }
        }

        if (!$user) {
            return;
        }

        $userId = $user->ID;

        update_user_meta($userId, 'admitad_uid', $uid);
        update_user_meta($userId, '_tagtag_gclid', $gclid);
    }

    public function handleOrderCreate($orderId)
    {
        $this->getContainer()->getAdmitadManager()->sendPostback($orderId);
        $order = new WC_Order($orderId);
        $order->update_meta_data(
            'admitad_uid',
            $this->getContainer()->getParameters()->getUserId()
        );
        $order->save_meta_data();
    }

    public function adminInit()
    {
        add_action('wp_enqueue_scripts', [$this, 'registerScripts']);

        $tabs = [
            new AuthTab($this->getContainer()),
            new MainTab($this->getContainer()),
            new RevisionTab($this->getContainer()),
            new ActionsTab($this->getContainer()),
            new PromoActionsTab($this->getContainer()),
        ];

        foreach ($tabs as $tab) {
            $tab->init();
        }
    }

    /**
     * Регистрация скриптов в админке.
     */
    public function registerScripts()
    {
        $scripts = [];

        $styles = [];

        foreach ($scripts as $name => $row) {
            wp_register_script($name, $row['src'], $row['deps'], $row['version']);
            wp_enqueue_script($name);
        }

        foreach ($styles as $name => $row) {
            wp_register_style($name, $row['src'], $row['deps'], $row['version']);
            wp_enqueue_style($name);
        }
    }

    public function adminMenu()
    {
        add_options_page(
            __('Admitad Tracking', 'admitadtracking'),
            __('Admitad Tracking', 'admitadtracking'),
            'manage_options',
            'admitadtracking',
            [
                $this, 'getOptionsPage',
            ]
        );
    }

    /**
     * Выводит контент страницы настроек.
     */
    public function getOptionsPage()
    {
        $current = $this->getCurrentActionName();

        ?>
        <div class="wrap">
            <h1>Admitad Tracking</h1>
            <h2 class="nav-tab-wrapper">
                <?php echo $this->getOptions(); ?>
            </h2>

            <form method="post" action="options.php">
                <?php
                    settings_fields('admitadtracking-' . $current . '-page');
        do_settings_sections('admitadtracking-' . $current . '-page');
        ?>
                <p>
                    <?php
                submit_button(
                    __('Save Changes', 'admitadtracking'),
                    'primary',
                    'submit',
                    false
                );
        ?>
                    <?php
            submit_button(
                __('Reset', 'admitadtracking'),
                'default',
                'reset',
                false
            );
        ?>
                </p>
            </form>
        </div> <?php
    }

    protected function getActionPage()
    {
        $current = $this->getCurrentActionName();

        ob_start();

        call_user_func([$this, $this->getTabs()[$current]['action']]);

        return ob_get_clean();
    }

    protected function getCurrentActionName()
    {
        $tabs = $this->getTabs();
        $currentName = array_key_first($tabs);

        foreach ($tabs as $name => $tab) {
            if (isset($_GET['action']) && $_GET['action'] == $name) {
                $currentName = $name;
            }
        }

        return $currentName;
    }

    /**
     * Возвращает массив с актуальными вкладками.
     */
    protected function getTabs(): array
    {
        $auth = get_option('admitadtracking_auth');

        if (!$auth['client_id'] || !$auth['client_secret']) {
            return ['auth' => ['name' => __('Auth', 'admitadtracking')]];
        }

        return [
            'main' => [
                'name' => __('Main', 'admitadtracking'),
            ],
            'actions' => [
                'name' => __('Actions', 'admitadtracking'),
            ],
            'promo_actions' => [
                'name' => __('Promo actions', 'admitadtracking'),
            ],
            'revision' => [
                'name' => __('Revision', 'admitadtracking'),
            ],
        ];
    }

    /**
     * В зависимости от выбранной вкладки возвращает соответствующий контент
     */
    protected function getOptions(): string
    {
        $current = $this->getCurrentActionName();

        ob_start();

        foreach ($this->getTabs() as $name => $options) {
            $url = admin_url('admin.php?page=admitadtracking&action=' . $name);
            ?>
            <a href="<?php echo $url; ?>" class="nav-tab <?php echo $name == $current ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e($options['name']); ?>
            </a>
            <?php
        }

        return ob_get_clean();
    }

    protected function getContainer(): AdmitadContainer
    {
        if (!$this->container) {
            $this->container = new AdmitadContainer();
        }

        return $this->container;
    }

    protected function getSettingName($name): string
    {
        return $this->prefix . '-' . $name;
    }

    protected function getSettingGroupName($name): string
    {
        return $this->prefix . '-' . $name . '-group';
    }

    private function get_asset_url($path): string
    {
        return str_replace(
            ['http:', 'https:'],
            '',
            plugins_url($path, __FILE__)
        );
    }
}
