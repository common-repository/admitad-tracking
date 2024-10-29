<?php

declare(strict_types=1);

namespace Admitad;

use WC_Order;
use WP_Post;

class AdmitadRevision
{
    public function __construct(protected AdmitadContainer $container)
    {
        $this->init();
    }

    public function init(): void
    {
        if ($name = $this->container->getSettings()->get('revision', 'name')) {
            add_rewrite_rule('^admitad\/' . $name . '\.xml$', 'index.php?admitad_revision=xml', 'top');
            add_filter('template_redirect', [$this, 'generateRevisionContent'], 1);
            add_filter('query_vars', [$this, 'addQuery'], 1);
            flush_rewrite_rules();
        }
    }

    public function addQuery($vars): array
    {
        $vars[] = 'admitad_revision';

        return $vars;
    }

    public function generateRevisionContent(): void
    {
        global $wp_query;

        if (isset($wp_query->query_vars['admitad_revision'])) {
            $wp_query->is_404 = false;

            $username = $this->container->getSettings()->get('revision', 'login');
            $secret = $this->container->getSettings()->get('revision', 'password');

            if (!isset($_SERVER['PHP_AUTH_USER']) || isset($_SERVER['PHP_AUTH_PW']) && ($_SERVER['PHP_AUTH_USER'] != $username || $_SERVER['PHP_AUTH_PW'] != $secret)) {
                header('WWW-Authenticate: Basic realm="Authorization"');
                header('HTTP/1.0 401 Unauthorized');
                exit;
            }

            header('Content-type: application/xml; charset=utf-8');
            echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";

            echo '<Payments xmlns="http://admitad.com/payments-revision" >' . "\n" .
                $this->getRevisionContent() .
                '</Payments>';

            exit;
        }
    }

    protected function getRevisionContent(): string
    {
        $posts = get_posts(
            [
                'numberposts' => -1,
                'post_type' => 'shop_order',
                'post_status' => array_keys(wc_get_order_statuses()),
            ]
        );

        $content = '';

        /** @var \WP_Post $post */
        foreach ($posts as $post) {
            $order = new \WC_Order($post->ID);

            if (!$this->hasAdmitadUid($order)) {
                continue;
            }

            if (!$status = $this->getOrderStatus($order)) {
                continue;
            }

            $content .=
                trim("
					<Payment>
						<OrderID>{$order->get_id()}</OrderID>
						<Status>{$status}</Status>
					</Payment>
				") . "\n";
        }

        return $content;
    }

    protected function getOrderStatus(\WC_Order $order): int
    {
        if (in_array($order->get_status(), ['cancelled', 'refunded', 'failed'])) {
            return 2;
        }

        if ('completed' == $order->get_status()) {
            return 1;
        }

        return 0;
    }

    protected function hasAdmitadUid(\WC_Order $order): bool
    {
        $data = $order->get_meta_data();

        foreach ($data as $object) {
            if ('admitad_uid' == $object->key) {
                return true;
            }
        }

        return true;
    }
}
