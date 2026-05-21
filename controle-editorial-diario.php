<?php
/**
 * Plugin Name: Controle Editorial Diário
 * Description: Relatório editorial para portais de notícias com contagem de postagens por editor, período, status, metas e exportação CSV.
 * Version: 1.0.1
 * Author: Lailson Oliveira
 * Author URI: https://lailson.dev
 * License: GPLv2 or later
 * Text Domain: controle-editorial-diario
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CED_VERSION', '1.0.1');
define('CED_PLUGIN_URL', plugin_dir_url(__FILE__));

add_action('admin_menu', 'ced_add_admin_menu');
add_action('admin_enqueue_scripts', 'ced_admin_assets');
add_action('admin_post_ced_export_csv', 'ced_export_csv');
add_action('wp_dashboard_setup', 'ced_register_dashboard_widget');

function ced_add_admin_menu() {
    add_menu_page(
        'Controle Editorial',
        'Controle Editorial',
        'manage_options',
        'controle-editorial-diario',
        'ced_render_page',
        'dashicons-chart-bar',
        25
    );
}

function ced_register_dashboard_widget() {
    wp_add_dashboard_widget(
        'ced_dashboard_widget',
        'Resumo Editorial de Hoje',
        'ced_dashboard_widget_render'
    );
}

function ced_admin_assets($hook) {
    if ($hook !== 'toplevel_page_controle-editorial-diario') {
        return;
    }

    wp_enqueue_style(
        'ced-admin-css',
        CED_PLUGIN_URL . 'assets/admin.css',
        array(),
        CED_VERSION
    );
}

function ced_allowed_statuses() {
    return array('publish', 'draft', 'pending', 'future');
}

function ced_sanitize_date($date, $fallback) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }

    return $fallback;
}

function ced_get_report_data($data_inicio, $data_fim, $status = 'publish') {
    global $wpdb;

    if (!in_array($status, ced_allowed_statuses(), true)) {
        $status = 'publish';
    }

    $inicio = $data_inicio . ' 00:00:00';
    $fim    = $data_fim . ' 23:59:59';

    return $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT 
                u.ID,
                u.display_name,
                u.user_email,
                COUNT(p.ID) as total
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->posts} p ON p.post_author = u.ID
            WHERE p.post_type = 'post'
            AND p.post_status = %s
            AND p.post_date BETWEEN %s AND %s
            GROUP BY u.ID
            ORDER BY total DESC
            ",
            $status,
            $inicio,
            $fim
        )
    );
}

function ced_render_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Você não tem permissão para acessar esta página.');
    }

    $hoje = current_time('Y-m-d');

    $data_inicio = isset($_GET['data_inicio']) ? ced_sanitize_date(sanitize_text_field(wp_unslash($_GET['data_inicio'])), $hoje) : $hoje;
    $data_fim    = isset($_GET['data_fim']) ? ced_sanitize_date(sanitize_text_field(wp_unslash($_GET['data_fim'])), $hoje) : $hoje;
    $status      = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'publish';
    $meta        = isset($_GET['meta']) ? absint($_GET['meta']) : 5;

    if ($meta < 1) {
        $meta = 1;
    }

    if (!in_array($status, ced_allowed_statuses(), true)) {
        $status = 'publish';
    }

    $resultados = ced_get_report_data($data_inicio, $data_fim, $status);
    $total_geral = 0;
    ?>

    <div class="wrap ced-wrap">
        <h1>Controle Editorial Diário</h1>

        <form method="get" class="ced-filtros">
            <input type="hidden" name="page" value="controle-editorial-diario">

            <label>
                <span>Data inicial</span>
                <input type="date" name="data_inicio" value="<?php echo esc_attr($data_inicio); ?>">
            </label>

            <label>
                <span>Data final</span>
                <input type="date" name="data_fim" value="<?php echo esc_attr($data_fim); ?>">
            </label>

            <label>
                <span>Status</span>
                <select name="status">
                    <option value="publish" <?php selected($status, 'publish'); ?>>Publicados</option>
                    <option value="draft" <?php selected($status, 'draft'); ?>>Rascunhos</option>
                    <option value="pending" <?php selected($status, 'pending'); ?>>Pendentes</option>
                    <option value="future" <?php selected($status, 'future'); ?>>Agendados</option>
                </select>
            </label>

            <label>
                <span>Meta por editor</span>
                <input type="number" name="meta" value="<?php echo esc_attr($meta); ?>" min="1">
            </label>

            <button class="button button-primary">Filtrar</button>

            <?php
            $export_url = wp_nonce_url(
                admin_url(
                    'admin-post.php?action=ced_export_csv&data_inicio=' . rawurlencode($data_inicio) .
                    '&data_fim=' . rawurlencode($data_fim) .
                    '&status=' . rawurlencode($status) .
                    '&meta=' . rawurlencode($meta)
                ),
                'ced_export_csv'
            );
            ?>

            <a class="button" href="<?php echo esc_url($export_url); ?>">
                Exportar CSV
            </a>
        </form>

        <table class="widefat striped ced-table">
            <thead>
                <tr>
                    <th>Editor</th>
                    <th>E-mail</th>
                    <th>Total</th>
                    <th>Meta</th>
                    <th>Status da meta</th>
                    <th>Posts</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!empty($resultados)) : ?>
                    <?php foreach ($resultados as $item) : ?>
                        <?php
                        $total = intval($item->total);
                        $total_geral += $total;
                        $bateu_meta = $total >= $meta;
                        ?>
                        <tr>
                            <td><?php echo esc_html($item->display_name); ?></td>
                            <td><?php echo esc_html($item->user_email); ?></td>
                            <td><strong><?php echo esc_html($total); ?></strong></td>
                            <td><?php echo esc_html($meta); ?></td>
                            <td>
                                <?php if ($bateu_meta) : ?>
                                    <span class="ced-badge ced-ok">Meta batida</span>
                                <?php else : ?>
                                    <span class="ced-badge ced-alert">Abaixo da meta</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=post&author=' . absint($item->ID))); ?>">
                                    Ver posts
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6">Nenhum post encontrado nesse período.</td>
                    </tr>
                <?php endif; ?>
            </tbody>

            <tfoot>
                <tr>
                    <th colspan="2">Total geral</th>
                    <th><?php echo esc_html($total_geral); ?></th>
                    <th colspan="3"></th>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php
}

function ced_export_csv() {
    if (!current_user_can('manage_options')) {
        wp_die('Você não tem permissão para exportar este relatório.');
    }

    check_admin_referer('ced_export_csv');

    $hoje = current_time('Y-m-d');

    $data_inicio = isset($_GET['data_inicio']) ? ced_sanitize_date(sanitize_text_field(wp_unslash($_GET['data_inicio'])), $hoje) : $hoje;
    $data_fim    = isset($_GET['data_fim']) ? ced_sanitize_date(sanitize_text_field(wp_unslash($_GET['data_fim'])), $hoje) : $hoje;
    $status      = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'publish';
    $meta        = isset($_GET['meta']) ? absint($_GET['meta']) : 5;

    if ($meta < 1) {
        $meta = 1;
    }

    $resultados = ced_get_report_data($data_inicio, $data_fim, $status);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=relatorio-editorial-' . $data_inicio . '-a-' . $data_fim . '.csv');

    $output = fopen('php://output', 'w');

    fputcsv($output, array('Editor', 'E-mail', 'Total', 'Meta', 'Status'));

    foreach ($resultados as $item) {
        $total = intval($item->total);

        fputcsv(
            $output,
            array(
                $item->display_name,
                $item->user_email,
                $total,
                $meta,
                $total >= $meta ? 'Meta batida' : 'Abaixo da meta'
            )
        );
    }

    fclose($output);
    exit;
}

function ced_dashboard_widget_render() {
    $hoje = current_time('Y-m-d');
    $resultados = ced_get_report_data($hoje, $hoje, 'publish');

    if (empty($resultados)) {
        echo '<p>Nenhuma postagem publicada hoje.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=controle-editorial-diario')) . '">Abrir Controle Editorial</a></p>';
        return;
    }

    echo '<ul>';

    foreach ($resultados as $item) {
        echo '<li><strong>' . esc_html($item->display_name) . ':</strong> ' . esc_html(intval($item->total)) . ' postagens</li>';
    }

    echo '</ul>';
    echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=controle-editorial-diario')) . '">Ver relatório completo</a></p>';
}
