<?php
// Include Stripe PHP library
require_once( CUSTOM_STRIPE_PAYMENT_PLUGIN_DIR . 'vendor/autoload.php' );

// if ( ! class_exists( 'WP_List_Table' ) ) {
//     require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
// }

class Stripe_Transactions_List_Table extends WP_List_Table {
    private $transactions = [];

    public function __construct($transactions) {
        parent::__construct([
            'singular' => __('Transaction', 'custom-stripe-payment-plugin'),
            'plural'   => __('Transactions', 'custom-stripe-payment-plugin'),
            'ajax'     => false,
        ]);

        $this->transactions = $transactions;
    }

    public function get_columns() {
        return [
            'id'          => __('Transaction ID', 'custom-stripe-payment-plugin'),
            'amount'      => __('Amount', 'custom-stripe-payment-plugin'),
            'currency'    => __('Currency', 'custom-stripe-payment-plugin'),
            'description' => __('Description', 'custom-stripe-payment-plugin'),
            'status'      => __('Status', 'custom-stripe-payment-plugin'),
            'created'     => __('Created', 'custom-stripe-payment-plugin'),
        ];
    }

    public function get_hidden_columns() {
        $screen = get_current_screen();
        $hidden = get_user_option("manage{$screen->id}columnshidden");
        return is_array($hidden) ? $hidden : [];
    }

    public function get_sortable_columns() {
        return [
            'id'      => ['id', false],
            'amount'  => ['amount', false],
            'created' => ['created', false],
        ];
    }

    private function usort_reorder($a, $b) {
        // If no sort, default to transaction id
        $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'id';
        // If no order, default to asc
        $order = (!empty($_GET['order'])) ? $_GET['order'] : 'asc';
        // Determine sort order
        $result = strcmp($a->$orderby, $b->$orderby);
        // Send final sort direction to usort
        return ($order === 'asc') ? $result : -$result;
    }
    
    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        // Handle pagination
        $per_page = $this->get_items_per_page('transactions_per_page', 10);
        $current_page = $this->get_pagenum();
        $total_items = count($this->transactions);

        // Handle search
        if (!empty($_REQUEST['s'])) {
            $this->transactions = array_filter($this->transactions, function($transaction) {
                $search = strtolower($_REQUEST['s']);
                return (strpos(strtolower($transaction->id), $search) !== false ||
                        strpos(strtolower($transaction->description), $search) !== false);
            });
        }

        // Handle sorting
        usort($this->transactions, [$this, 'usort_reorder']);

        // Paginate the items
        $this->transactions = array_slice($this->transactions, (($current_page - 1) * $per_page), $per_page);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);

        $this->items = $this->transactions;
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return esc_html($item->id);
            case 'amount':
                return esc_html(number_format($item->amount_received / 100, 2));
            case 'currency':
                return esc_html(strtoupper($item->currency));
            case 'description':
                return esc_html($item->description ?? 'N/A');
            case 'status':
                return esc_html($item->status);
            case 'created':
                return esc_html(date('Y-m-d H:i:s', $item->created));
            default:
                return print_r($item, true);
        }
    }


    public function get_bulk_actions() {
        return [];
    }

    public function extra_tablenav($which) {
        if ($which == 'top') {
            echo '<div class="alignleft actions">';
            $this->search_box(__('Search Transactions', 'custom-stripe-payment-plugin'), 'search_id');
            echo '</div>';
        }
    }
}


// Function to retrieve and display Stripe transactions
function display_stripe_transactions() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Initialize Stripe API with your secret key
    \Stripe\Stripe::setApiKey(custom_stripe_payment_plugin_get_secret_key());

    // Define parameters for fetching PaymentIntents
    $params = [
        'limit' => 50,
        'expand' => ['data.payment_method'],
    ];

    // Query Stripe for PaymentIntents
    try {
        $payment_intents = \Stripe\PaymentIntent::all($params);
    } catch (Exception $e) {
        echo '<div class="error"><p>Error fetching transactions: ' . esc_html($e->getMessage()) . '</p></div>';
        return;
    }

    // Instantiate the list table class
    $transactions_list_table = new Stripe_Transactions_List_Table($payment_intents->data);
    $transactions_list_table->prepare_items();

    ?>
    <div class="wrap">
        <h1><?php echo esc_html('Stripe Transactions'); ?></h1>
        <form method="get">
            <input type="hidden" name="page" value="custom-stripe-payment-plugin-transactions">
            <?php
            $transactions_list_table->display();
            ?>
        </form>
    </div>
    <?php
}

function add_transactions_screen_options() {
    $screen = get_current_screen();
    if (!is_object($screen) || $screen->id != 'stripe-plugin_page_custom-stripe-payment-plugin-transactions') {
        return;
    }

    add_screen_option('per_page', [
        'label'   => __('Transactions per page', 'custom-stripe-payment-plugin'),
        'default' => 10,
        'option'  => 'transactions_per_page',
    ]);

    add_filter("manage_{$screen->id}_columns", 'stripe_transactions_columns');
}
add_action('admin_head', 'add_transactions_screen_options');

function set_transactions_screen_option($status, $option, $value) {
    if ('transactions_per_page' === $option) {
        return $value;
    }

    return $status;
}
add_filter('set-screen-option', 'set_transactions_screen_option', 10, 3);

function stripe_transactions_columns($columns) {
    return [
        'id'          => __('Transaction ID', 'custom-stripe-payment-plugin'),
        'amount'      => __('Amount', 'custom-stripe-payment-plugin'),
        'currency'    => __('Currency', 'custom-stripe-payment-plugin'),
        'description' => __('Description', 'custom-stripe-payment-plugin'),
        'status'      => __('Status', 'custom-stripe-payment-plugin'),
        'created'     => __('Created', 'custom-stripe-payment-plugin'),
    ];
}

function save_transactions_hidden_columns() {
    if (isset($_POST['wp_screen_options'])) {
        $hidden_columns = isset($_POST['hidden']) ? (array) $_POST['hidden'] : [];
        update_user_meta(get_current_user_id(), 'stripe_transactions_hidden_columns', $hidden_columns);
    }
}
add_action('admin_head', 'save_transactions_hidden_columns');


// Ensure the function exists before including
if ( ! function_exists( 'custom_stripe_payment_plugin_get_secret_key' ) ) {
    // Include stripe-init.php to access the function
    require_once( CUSTOM_STRIPE_PAYMENT_PLUGIN_DIR . 'includes/stripe-init.php' );
}


// Now you can safely use custom_stripe_payment_plugin_get_secret_key() here if needed
// Example function that uses the secret key function
function custom_stripe_payment_plugin_process_transaction() {
    $secret_key = custom_stripe_payment_plugin_get_secret_key();
    // Process transaction using the secret key
}