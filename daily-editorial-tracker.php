<?php
/**
 * Plugin Name: Daily Editorial Tracker
 * Plugin URI: https://github.com/devlailson/daily-editorial-tracker
 * Description: Track daily editorial activity by author, date range, post status, goals, and CSV reports.
 * Version: 1.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 6.8
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

define('DAILY_EDITORIAL_TRACKER_VERSION', '1.2.0');
define('DAILY_EDITORIAL_TRACKER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DAILY_EDITORIAL_TRACKER_GOAL_META_KEY', '_daily_editorial_tracker_goal');

add_action('init', 'daily_editorial_tracker_load_textdomain');
add_action('admin_menu', 'daily_editorial_tracker_add_admin_menu');
add_action('admin_enqueue_scripts', 'daily_editorial_tracker_admin_assets');
add_action('admin_post_daily_editorial_tracker_export_csv', 'daily_editorial_tracker_export_csv');
add_action('admin_post_daily_editorial_tracker_save_goals', 'daily_editorial_tracker_save_goals');
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

/**
 * Get an author's saved editorial goal.
 */
function daily_editorial_tracker_get_author_goal($user_id) {
    $goal = absint(get_user_meta($user_id, DAILY_EDITORIAL_TRACKER_GOAL_META_KEY, true));

    return $goal > 0 ? $goal : 5;
}

/**
 * Save the individual goals configured for authors.
 */
function daily_editorial_tracker_save_goals() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to manage author goals.', 'daily-editorial-tracker'));
    }

    check_admin_referer('daily_editorial_tracker_save_goals');

    $goals = isset($_POST['author_goals']) && is_array($_POST['author_goals'])
        ? wp_unslash($_POST['author_goals'])
        : array();

    foreach ($goals as $user_id => $goal) {
        $user_id = absint($user_id);
        $goal    = absint($goal);

        if (!$user_id || !get_userdata($user_id) || !user_can($user_id, 'edit_posts')) {
            continue;
        }

        update_user_meta(
            $user_id,
            DAILY_EDITORIAL_TRACKER_GOAL_META_KEY,
            max(1, min(9999, $goal))
        );
    }

    wp_safe_redirect(
        add_query_arg(
            array(
                'page'          => 'daily-editorial-tracker',
                'goals-updated' => '1',
            ),
            admin_url('tools.php')
        )
    );
    exit;
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
    if (!in_array($status, daily_editorial_tracker_allowed_statuses(), true)) {
        $status = 'publish';
    }

    $results = daily_editorial_tracker_get_report_data($start_date, $end_date, $status);
    $authors = get_users(
        array(
            'capability' => 'edit_posts',
            'fields'     => array('ID', 'display_name', 'user_email'),
            'orderby'    => 'display_name',
            'order'      => 'ASC',
        )
    );
    $grand_total = 0;
    ?>

    <div class="wrap ced-wrap">
        <h1><?php echo esc_html__('Daily Editorial Tracker', 'daily-editorial-tracker'); ?></h1>

        <?php if (isset($_GET['goals-updated']) && '1' === sanitize_text_field(wp_unslash($_GET['goals-updated']))) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html__('Author goals saved successfully.', 'daily-editorial-tracker'); ?></p>
            </div>
        <?php endif; ?>

        <div class="ced-goals">
            <h2><?php echo esc_html__('Individual author goals', 'daily-editorial-tracker'); ?></h2>
            <p><?php echo esc_html__('Set a different editorial goal for each author.', 'daily-editorial-tracker'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="daily_editorial_tracker_save_goals">
                <?php wp_nonce_field('daily_editorial_tracker_save_goals'); ?>

                <div class="ced-goal-grid">
                    <?php foreach ($authors as $author) : ?>
                        <label>
                            <span>
                                <?php echo esc_html($author->display_name); ?>
                                <small><?php echo esc_html($author->user_email); ?></small>
                            </span>
                            <input
                                type="number"
                                name="author_goals[<?php echo esc_attr($author->ID); ?>]"
                                value="<?php echo esc_attr(daily_editorial_tracker_get_author_goal($author->ID)); ?>"
                                min="1"
                                max="9999"
                            >
                        </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="button button-primary">
                    <?php echo esc_html__('Save author goals', 'daily-editorial-tracker'); ?>
                </button>
            </form>
        </div>

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

            <button class="button button-primary"><?php echo esc_html__('Filter', 'daily-editorial-tracker'); ?></button>

            <?php
            $export_url = wp_nonce_url(
                admin_url(
                    'admin-post.php?action=daily_editorial_tracker_export_csv&start_date=' . rawurlencode($start_date) .
                    '&end_date=' . rawurlencode($end_date) .
                    '&status=' . rawurlencode($status)
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
                        $goal = daily_editorial_tracker_get_author_goal($item->ID);
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
        $goal  = daily_editorial_tracker_get_author_goal($item->ID);

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
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('tools.php?page=daily-editorial-tracker')) . '">' . esc_html__('Open Editorial Tracker', 'daily-editorial-tracker') . '</a></p>';
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
    echo '<p><a class="button button-primary" href="' . esc_url(admin_url('tools.php?page=daily-editorial-tracker')) . '">' . esc_html__('View full report', 'daily-editorial-tracker') . '</a></p>';
}
