<?php
/**
 * Plugin Name: Daily Editorial Tracker
 * Plugin URI: https://github.com/devlailson/daily-editorial-tracker
 * Description: Track daily editorial activity by author, date range, post status, goals, and CSV reports.
 * Version: 1.1.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 7.1
 * Author: Lailson Oliveira
 * Author URI: https://lailson.dev
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: daily-editorial-tracker
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DAILY_EDITORIAL_TRACKER_VERSION', '1.1.2');
define('DAILY_EDITORIAL_TRACKER_PLUGIN_URL', plugin_dir_url(__FILE__));

add_action('init', 'daily_editorial_tracker_load_textdomain');
add_action('admin_menu', 'daily_editorial_tracker_add_admin_menu');
add_action('admin_enqueue_scripts', 'daily_editorial_tracker_admin_assets');
add_action('admin_post_daily_editorial_tracker_export_csv', 'daily_editorial_tracker_export_csv');
add_action('wp_dashboard_setup', 'daily_editorial_tracker_register_dashboard_widget');

/**
 * Load the translation files bundled with the plugin.
 */
function daily_editorial_tracker_load_textdomain() {
    load_plugin_textdomain(
        'daily-editorial-tracker',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}

function daily_editorial_tracker_add_admin_menu() {
    add_management_page(
        __('Editorial Tracker', 'daily-editorial-tracker'),
        __('Editorial Tracker', 'daily-editorial-tracker'),
        'manage_options',
        'daily-editorial-tracker',
        'daily_editorial_tracker_render_page'
    );
}

function daily_editorial_tracker_register_dashboard_widget() {
    if (!current_user_can('manage_options')) {
        return;
    }

    wp_add_dashboard_widget(
        'daily_editorial_tracker_dashboard_widget',
        __('Today\'s Editorial Summary', 'daily-editorial-tracker'),
        'daily_editorial_tracker_dashboard_widget_render'
    );
}

function daily_editorial_tracker_admin_assets($hook) {
    if ($hook !== 'tools_page_daily-editorial-tracker') {
        return;
    }

    wp_enqueue_style(
        'daily-editorial-tracker-admin',
        DAILY_EDITORIAL_TRACKER_PLUGIN_URL . 'assets/admin.css',
        array(),
        DAILY_EDITORIAL_TRACKER_VERSION
    );
}

function daily_editorial_tracker_allowed_statuses() {
    return array('publish', 'draft', 'pending', 'future');
}

function daily_editorial_tracker_sanitize_date($date, $fallback) {
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
        if (checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            return $date;
        }
    }

    return $fallback;
}

function daily_editorial_tracker_get_report_data($data_inicio, $data_fim, $status = 'publish') {
    global $wpdb;

    if (!in_array($status, daily_editorial_tracker_allowed_statuses(), true)) {
        $status = 'publish';
    }

    $inicio = $data_inicio . ' 00:00:00';
    $fim    = $data_fim . ' 23:59:59';

    // A fresh result is required because post counts can change at any time.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    return $wpdb->get_results(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

function daily_editorial_tracker_render_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'daily-editorial-tracker'));
    }

    $today = current_time('Y-m-d');
    $valid_filter_nonce = isset($_GET['daily_editorial_tracker_filter_nonce']) && wp_verify_nonce(
        sanitize_text_field(wp_unslash($_GET['daily_editorial_tracker_filter_nonce'])),
        'daily_editorial_tracker_filter'
    );

    $start_date = ($valid_filter_nonce && isset($_GET['start_date'])) ? daily_editorial_tracker_sanitize_date(sanitize_text_field(wp_unslash($_GET['start_date'])), $today) : $today;
    $end_date   = ($valid_filter_nonce && isset($_GET['end_date'])) ? daily_editorial_tracker_sanitize_date(sanitize_text_field(wp_unslash($_GET['end_date'])), $today) : $today;
    $status     = ($valid_filter_nonce && isset($_GET['status'])) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'publish';
    $goal       = ($valid_filter_nonce && isset($_GET['goal'])) ? absint($_GET['goal']) : 5;

    if ($goal < 1) {
        $goal = 1;
    }

    if (!in_array($status, daily_editorial_tracker_allowed_statuses(), true)) {
        $status = 'publish';
    }

    $results = daily_editorial_tracker_get_report_data($start_date, $end_date, $status);
    $grand_total = 0;
    ?>

    <div class="wrap ced-wrap">
        <h1><?php echo esc_html__('Daily Editorial Tracker', 'daily-editorial-tracker'); ?></h1>

        <form method="get" class="ced-filtros">
            <input type="hidden" name="page" value="daily-editorial-tracker">
            <?php wp_nonce_field('daily_editorial_tracker_filter', 'daily_editorial_tracker_filter_nonce'); ?>

            <label>
                <span><?php echo esc_html__('Start date', 'daily-editorial-tracker'); ?></span>
                <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
            </label>

            <label>
                <span><?php echo esc_html__('End date', 'daily-editorial-tracker'); ?></span>
                <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
            </label>

            <label>
                <span><?php echo esc_html__('Status', 'daily-editorial-tracker'); ?></span>
                <select name="status">
                    <option value="publish" <?php selected($status, 'publish'); ?>><?php echo esc_html__('Published', 'daily-editorial-tracker'); ?></option>
                    <option value="draft" <?php selected($status, 'draft'); ?>><?php echo esc_html__('Drafts', 'daily-editorial-tracker'); ?></option>
                    <option value="pending" <?php selected($status, 'pending'); ?>><?php echo esc_html__('Pending', 'daily-editorial-tracker'); ?></option>
                    <option value="future" <?php selected($status, 'future'); ?>><?php echo esc_html__('Scheduled', 'daily-editorial-tracker'); ?></option>
                </select>
            </label>

            <label>
                <span><?php echo esc_html__('Goal per author', 'daily-editorial-tracker'); ?></span>
                <input type="number" name="goal" value="<?php echo esc_attr($goal); ?>" min="1">
            </label>

            <button class="button button-primary"><?php echo esc_html__('Filter', 'daily-editorial-tracker'); ?></button>

            <?php
            $export_url = wp_nonce_url(
                admin_url(
                    'admin-post.php?action=daily_editorial_tracker_export_csv&start_date=' . rawurlencode($start_date) .
                    '&end_date=' . rawurlencode($end_date) .
                    '&status=' . rawurlencode($status) .
                    '&goal=' . rawurlencode($goal)
                ),
                'daily_editorial_tracker_export_csv'
            );
            ?>

            <a class="button" href="<?php echo esc_url($export_url); ?>">
                <?php echo esc_html__('Export CSV', 'daily-editorial-tracker'); ?>
            </a>
        </form>

        <table class="widefat striped ced-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Author', 'daily-editorial-tracker'); ?></th>
                    <th><?php echo esc_html__('Email', 'daily-editorial-tracker'); ?></th>
                    <th><?php echo esc_html__('Total', 'daily-editorial-tracker'); ?></th>
                    <th><?php echo esc_html__('Goal', 'daily-editorial-tracker'); ?></th>
                    <th><?php echo esc_html__('Goal status', 'daily-editorial-tracker'); ?></th>
                    <th><?php echo esc_html__('Posts', 'daily-editorial-tracker'); ?></th>
                </tr>
            </thead>

            <tbody>
                <?php if (!empty($results)) : ?>
                    <?php foreach ($results as $item) : ?>
                        <?php
                        $total = intval($item->total);
                        $grand_total += $total;
                        $goal_reached = $total >= $goal;
                        ?>
                        <tr>
                            <td><?php echo esc_html($item->display_name); ?></td>
                            <td><?php echo esc_html($item->user_email); ?></td>
                            <td><strong><?php echo esc_html($total); ?></strong></td>
                            <td><?php echo esc_html($goal); ?></td>
                            <td>
                                <?php if ($goal_reached) : ?>
                                    <span class="ced-badge ced-ok"><?php echo esc_html__('Goal reached', 'daily-editorial-tracker'); ?></span>
                                <?php else : ?>
                                    <span class="ced-badge ced-alert"><?php echo esc_html__('Below goal', 'daily-editorial-tracker'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=post&author=' . absint($item->ID))); ?>">
                                    <?php echo esc_html__('View posts', 'daily-editorial-tracker'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6"><?php echo esc_html__('No posts found for this period.', 'daily-editorial-tracker'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>

            <tfoot>
                <tr>
                    <th colspan="2"><?php echo esc_html__('Grand total', 'daily-editorial-tracker'); ?></th>
                    <th><?php echo esc_html($grand_total); ?></th>
                    <th colspan="3"></th>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php
}

function daily_editorial_tracker_export_csv() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to export this report.', 'daily-editorial-tracker'));
    }

    check_admin_referer('daily_editorial_tracker_export_csv');

    $today = current_time('Y-m-d');

    $start_date = isset($_GET['start_date']) ? daily_editorial_tracker_sanitize_date(sanitize_text_field(wp_unslash($_GET['start_date'])), $today) : $today;
    $end_date   = isset($_GET['end_date']) ? daily_editorial_tracker_sanitize_date(sanitize_text_field(wp_unslash($_GET['end_date'])), $today) : $today;
    $status     = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'publish';
    $goal       = isset($_GET['goal']) ? absint($_GET['goal']) : 5;

    if ($goal < 1) {
        $goal = 1;
    }

    $results = daily_editorial_tracker_get_report_data($start_date, $end_date, $status);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=editorial-report-' . $start_date . '-to-' . $end_date . '.csv');

    $output = fopen('php://output', 'w');

    fputcsv(
        $output,
        array(
            __('Author', 'daily-editorial-tracker'),
            __('Email', 'daily-editorial-tracker'),
            __('Total', 'daily-editorial-tracker'),
            __('Goal', 'daily-editorial-tracker'),
            __('Status', 'daily-editorial-tracker'),
        )
    );

    foreach ($results as $item) {
        $total = intval($item->total);

        fputcsv(
            $output,
            array(
                daily_editorial_tracker_escape_csv_formula($item->display_name),
                daily_editorial_tracker_escape_csv_formula($item->user_email),
                $total,
                $goal,
                $total >= $goal
                    ? __('Goal reached', 'daily-editorial-tracker')
                    : __('Below goal', 'daily-editorial-tracker')
            )
        );
    }

    exit;
}

/**
 * Prevent spreadsheet applications from interpreting user data as formulas.
 *
 * @param string $value CSV cell value.
 * @return string
 */
function daily_editorial_tracker_escape_csv_formula($value) {
    $value = (string) $value;

    if (preg_match('/^[=+\-@]/', $value)) {
        return "'" . $value;
    }

    return $value;
}

function daily_editorial_tracker_dashboard_widget_render() {
    $today = current_time('Y-m-d');
    $results = daily_editorial_tracker_get_report_data($today, $today, 'publish');

    if (empty($results)) {
        echo '<p>' . esc_html__('No posts published today.', 'daily-editorial-tracker') . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=daily-editorial-tracker')) . '">' . esc_html__('Open Editorial Tracker', 'daily-editorial-tracker') . '</a></p>';
        return;
    }

    echo '<ul>';

    foreach ($results as $item) {
        $post_count = (int) $item->total;
        $post_label = sprintf(
            /* translators: %d: Number of posts. */
            _n('%d post', '%d posts', $post_count, 'daily-editorial-tracker'),
            $post_count
        );
        echo '<li><strong>' . esc_html($item->display_name) . ':</strong> ' . esc_html($post_label) . '</li>';
    }

    echo '</ul>';
    echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=daily-editorial-tracker')) . '">' . esc_html__('View full report', 'daily-editorial-tracker') . '</a></p>';
}
