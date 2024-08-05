<?php
// Admin menu page setup
add_action( 'admin_menu', 'custom_stripe_payment_plugin_menu' );

function custom_stripe_payment_plugin_menu() {
    add_menu_page(
        'Stripe Payment Plugin',
        'Stripe Plugin',
        'manage_options',
        'custom-stripe-payment-plugin',
        'custom_stripe_payment_plugin_options_page'
    );

    add_submenu_page( 
        'custom-stripe-payment-plugin',
        'Stripe Forms',
        'Stripe Forms',
        'manage_options',
        'edit.php?post_type=stripe_payment'
    );

    // Add submenu page for transactions
    add_submenu_page(
        'custom-stripe-payment-plugin',
        'Stripe Transactions',
        'Transactions',
        'manage_options',
        'custom-stripe-payment-plugin-transactions',
        'custom_stripe_payment_plugin_transactions_page'
    );

    add_submenu_page(
        'custom-stripe-payment-plugin',  
        'Stripe Products',
        'Stripe Products',
        'manage_options',
        'stripe-products',
        'display_stripe_products_page'
    );

    add_submenu_page(
        'custom-stripe-payment-plugin', // Parent menu slug
        'User Information', // Page title
        'User Information', // Menu title
        'manage_options', // Capability
        'custom-stripe-user-info', // Menu slug
        'custom_stripe_user_info_callback' // Callback function
    );

}

// Callback function for options page
function custom_stripe_payment_plugin_options_page() {
    // Display settings form and handle form submission here
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Handle form submission
    if ( isset( $_POST['submit'] ) ) {
        // Process and save form data
        update_option( 'stripe_live_mode', isset( $_POST['stripe_live_mode'] ) ? 1 : 0 );
        update_option( 'stripe_live_secret_key', sanitize_text_field( $_POST['stripe_live_secret_key'] ) );
        update_option( 'stripe_sandbox_publishable_key', sanitize_text_field( $_POST['stripe_sandbox_publishable_key'] ) );
        update_option( 'stripe_sandbox_secret_key', sanitize_text_field( $_POST['stripe_sandbox_secret_key'] ) );
        update_option( 'stripe_success_page_url', esc_url_raw( $_POST['stripe_success_page_url'] ) );
        update_option( 'stripe_failure_page_url', esc_url_raw( $_POST['stripe_failure_page_url'] ) );

        // Additional fields handling for product creation, subscriptions, checkout options, etc.
    }

    // Display the settings form
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Live Mode', 'custom-stripe-payment-plugin' ); ?></th>
                    <td><label><input type="checkbox" name="stripe_live_mode" value="1" <?php checked( get_option( 'stripe_live_mode', false ) ); ?>> <?php esc_html_e( 'Enable Live Mode', 'custom-stripe-payment-plugin' ); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Live Publishable Key', 'custom-stripe-payment-plugin' ); ?></th>
                    <td><input type="text" name="stripe_live_publishable_key" value="<?php echo esc_attr( get_option( 'stripe_live_publishable_key', '' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Live Secret Key', 'custom-stripe-payment-plugin' ); ?></th>
                    <td><input type="text" name="stripe_live_secret_key" value="<?php echo esc_attr( get_option( 'stripe_live_secret_key', '' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Sandbox Publishable Key', 'custom-stripe-payment-plugin' ); ?></th>
                    <td><input type="text" name="stripe_sandbox_publishable_key" value="<?php echo esc_attr( get_option( 'stripe_sandbox_publishable_key', '' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Sandbox Secret Key', 'custom-stripe-payment-plugin' ); ?></th>
                    <td><input type="text" name="stripe_sandbox_secret_key" value="<?php echo esc_attr( get_option( 'stripe_sandbox_secret_key', '' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Success Page URL', 'custom-stripe-payment-plugin' ); ?></th>
                    <td><input type="url" name="stripe_success_page_url" value="<?php echo esc_url( get_option( 'stripe_success_page_url', '' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Failure Page URL', 'custom-stripe-payment-plugin' ); ?></th>
                    <td><input type="url" name="stripe_failure_page_url" value="<?php echo esc_url( get_option( 'stripe_failure_page_url', '' ) ); ?>" class="regular-text"></td>
                </tr>
                <!-- Additional fields for product creation, subscriptions, checkout options, etc. -->
            </table>
            <?php submit_button( 'Save Settings' ); ?>
        </form>

        <?php if(!empty(get_option( 'stripe_sandbox_secret_key' ))){ ?>
            <h2><?php esc_html_e( 'Test Transaction', 'custom-stripe-payment-plugin' ); ?></h2>
            <form id="stripe-test-form">
                <input type="hidden" name="action" value="custom_stripe_test_transaction">
                <button type="submit"><?php esc_html_e( 'Run Test Transaction', 'custom-stripe-payment-plugin' ); ?></button>
            </form>
            <div id="stripe-test-result"></div>
        <?php } ?>
    </div>
    <?php
}

// AJAX handler for test transaction
function custom_stripe_test_transaction() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    // Initialize Stripe with the correct API key
    \Stripe\Stripe::setApiKey( custom_stripe_payment_plugin_get_secret_key() );

    try {
        // Create a test payment intent
        $payment_intent = \Stripe\PaymentIntent::create([
            'amount' => 1000, // $10.00
            'currency' => 'usd',
            'payment_method_types' => ['card'],
        ]);

        wp_send_json_success( 'Test transaction successful: ' . $payment_intent->id );
    } catch ( Exception $e ) {
        wp_send_json_error( 'Error creating test transaction: ' . $e->getMessage() );
    }
}
add_action( 'wp_ajax_custom_stripe_test_transaction', 'custom_stripe_test_transaction' );

// Enqueue script to handle test transaction form submission
function custom_stripe_payment_plugin_admin_scripts() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('stripe-test-form').addEventListener('submit', function(e) {
            e.preventDefault();

            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    var resultDiv = document.getElementById('stripe-test-result');
                    if (response.success) {
                        resultDiv.innerHTML = '<p>' + response.data + '</p>';
                    } else {
                        resultDiv.innerHTML = '<p>Error: ' + response.data + '</p>';
                    }
                }
            };

            var formData = new FormData(document.getElementById('stripe-test-form'));
            var params = new URLSearchParams(formData).toString();
            xhr.send(params);
        });
    });
    </script>
    <?php
}
add_action( 'admin_footer', 'custom_stripe_payment_plugin_admin_scripts' );

// Callback function for transactions page
function custom_stripe_payment_plugin_transactions_page() {
    // Display transactions
    display_stripe_transactions();
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Stripe_Products_List_Table extends WP_List_Table {
    private $products = [];

    public function __construct($products) {
        parent::__construct([
            'singular' => __('Product', 'custom-stripe-payment-plugin'),
            'plural'   => __('Products', 'custom-stripe-payment-plugin'),
            'ajax'     => false,
        ]);

        $this->products = $products;
    }

    public function get_columns() {
        return [
            'id'          => __('Product ID', 'custom-stripe-payment-plugin'),
            'name'        => __('Name', 'custom-stripe-payment-plugin'),
            'description' => __('Description', 'custom-stripe-payment-plugin'),
            'price'       => __('Price (USD)', 'custom-stripe-payment-plugin'),
            'type'        => __('Type', 'custom-stripe-payment-plugin'),
            'interval'    => __('Interval', 'custom-stripe-payment-plugin'),
            'created'     => __('Created', 'custom-stripe-payment-plugin'),
        ];
    }

    public function get_hidden_columns() {
        $screen = get_current_screen();
        $hidden = get_user_option("manage{$screen->id}columnshidden");
        return is_array($hidden) ? $hidden : [];
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        // Handle pagination
        $per_page = $this->get_items_per_page('products_per_page', 10);
        $current_page = $this->get_pagenum();
        $total_items = count($this->products);

        // Handle search
        if (!empty($_REQUEST['s'])) {
            $this->products = array_filter($this->products, function($product) {
                $search = strtolower($_REQUEST['s']);
                return (strpos(strtolower($product->id), $search) !== false ||
                        strpos(strtolower($product->name), $search) !== false ||
                        strpos(strtolower($product->description), $search) !== false);
            });
        }

        // Handle filter
        if (!empty($_REQUEST['type'])) {
            $this->products = array_filter($this->products, function($product) {
                $filter = strtolower($_REQUEST['type']);
                $prices = \Stripe\Price::all(['product' => $product->id]);
                $price = !empty($prices->data) ? $prices->data[0] : null;
                $type = $price && $price->type === 'recurring' ? 'recurring' : 'one-off';
                return $type === $filter;
            });
        }

        // Paginate the items
        $this->products = array_slice($this->products, (($current_page - 1) * $per_page), $per_page);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);

        $this->items = $this->products;
    }

    public function get_sortable_columns() {
        return [
            'id'      => ['id', false],
            'name'    => ['name', false],
            'created' => ['created', false],
        ];
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return esc_html($item->id);
            case 'name':
                return esc_html($item->name);
            case 'description':
                return esc_html($item->description);
            case 'price':
                $prices = \Stripe\Price::all(['product' => $item->id]);
                $price = !empty($prices->data) ? $prices->data[0] : null;
                return esc_html($price ? number_format($price->unit_amount / 100, 2) : 'N/A');
            case 'type':
                $prices = \Stripe\Price::all(['product' => $item->id]);
                $price = !empty($prices->data) ? $prices->data[0] : null;
                return esc_html($price && $price->type === 'recurring' ? 'Recurring' : 'One-off');
            case 'interval':
                $prices = \Stripe\Price::all(['product' => $item->id]);
                $price = !empty($prices->data) ? $prices->data[0] : null;
                if ($price && $price->type === 'recurring') {
                    return esc_html(ucfirst($price->recurring->interval));
                }
                return 'N/A';
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
            $this->search_box(__('Search Products', 'custom-stripe-payment-plugin'), 'search_id');

            echo '<select name="type">';
            echo '<option value="">' . __('All Types', 'custom-stripe-payment-plugin') . '</option>';
            echo '<option value="recurring" ' . selected($_REQUEST['type'], 'recurring', false) . '>' . __('Recurring', 'custom-stripe-payment-plugin') . '</option>';
            echo '<option value="one-off" ' . selected($_REQUEST['type'], 'one-off', false) . '>' . __('One-off', 'custom-stripe-payment-plugin') . '</option>';
            echo '</select>';

            submit_button(__('Filter'), '', 'filter_action', false);
            echo '</div>';
        }
    }
}

class Stripe_Customers_List_Table extends WP_List_Table {
    private $customers = [];

    public function __construct($customers) {
        parent::__construct([
            'singular' => __('Customer', 'custom-stripe-payment-plugin'),
            'plural'   => __('Customers', 'custom-stripe-payment-plugin'),
            'ajax'     => false,
        ]);

        $this->customers = $customers;
    }

    public function get_columns() {
        return apply_filters('manage_stripe-customers_columns', [
            'cb'                     => '<input type="checkbox" />',
            'id'                     => __('ID', 'custom-stripe-payment-plugin'),
            'name'                   => __('Name', 'custom-stripe-payment-plugin'),
            'email'                  => __('Email', 'custom-stripe-payment-plugin'),
            'default_payment_method' => __('Default Payment Method', 'custom-stripe-payment-plugin'),
            'created'                => __('Created', 'custom-stripe-payment-plugin'),
            'description'            => __('Description', 'custom-stripe-payment-plugin'),
            'subscription_name'      => __('Subscription Name', 'custom-stripe-payment-plugin'),
            'subscription_status'    => __('Subscription Status', 'custom-stripe-payment-plugin'),
        ]);
    }

    // public function get_hidden_columns() {
    //     $hidden = get_user_meta(get_current_user_id(), 'stripe_customers_hidden_columns', true);
    //     return is_array($hidden) ? $hidden : [];
    // }

    public function get_hidden_columns() {
        $screen = get_current_screen();
        $hidden = get_user_option("manage{$screen->id}columnshidden");
        return is_array($hidden) ? $hidden : [];
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        // Handle pagination
        $per_page = $this->get_items_per_page('customers_per_page', 10);
        $current_page = $this->get_pagenum();
        $total_items = count($this->customers);

        // Handle search
        if (!empty($_REQUEST['s'])) {
            $this->customers = array_filter($this->customers, function($customer) {
                $search = strtolower($_REQUEST['s']);
                return (strpos(strtolower($customer->id), $search) !== false ||
                        strpos(strtolower($customer->name), $search) !== false ||
                        strpos(strtolower($customer->email), $search) !== false);
            });
        }

        // Handle filter
        if (!empty($_REQUEST['subscription_status'])) {
            $this->customers = array_filter($this->customers, function($customer) {
                $filter = strtolower($_REQUEST['subscription_status']);
                $subscriptions = \Stripe\Subscription::all(['customer' => $customer->id]);
                $active_subscription = false;
                foreach ($subscriptions->data as $subscription) {
                    if ($subscription->status === 'active') {
                        $active_subscription = true;
                        break;
                    }
                }
                return $filter === 'active' ? $active_subscription : !$active_subscription;
            });
        }

        // Paginate the items
        $this->customers = array_slice($this->customers, (($current_page - 1) * $per_page), $per_page);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);

        $this->items = $this->customers;
    }

    public function get_sortable_columns() {
        return [
            'id'      => ['id', false],
            'name'    => ['name', false],
            'created' => ['created', false],
        ];
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return esc_html($item->id);
            case 'name':
                return esc_html($item->name ?? 'N/A');
            case 'email':
                return esc_html($item->email);
            case 'default_payment_method':
                return esc_html(isset($item->invoice_settings->default_payment_method) ? $item->invoice_settings->default_payment_method : 'N/A');
            case 'created':
                return esc_html(date('Y-m-d H:i:s', $item->created));
            case 'description':
                return esc_html($item->description ?? 'N/A');
            case 'subscription_name':
                $subscriptions = \Stripe\Subscription::all(['customer' => $item->id]);
                if (!empty($subscriptions->data)) {
                    foreach ($subscriptions->data as $subscription) {
                        if ($subscription->status === 'active') {
                            return esc_html($subscription->plan->nickname);
                        }
                    }
                }
                return 'No subscriptions';
            case 'subscription_status':
                $subscriptions = \Stripe\Subscription::all(['customer' => $item->id]);
                $active_subscription = false;
                foreach ($subscriptions->data as $subscription) {
                    if ($subscription->status === 'active') {
                        $active_subscription = true;
                        break;
                    }
                }
                return esc_html($active_subscription ? 'Active' : 'Inactive');
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
            $this->search_box(__('Search Customers', 'custom-stripe-payment-plugin'), 'search_id');

            echo '<select name="subscription_status">';
            echo '<option value="">' . __('All Statuses', 'custom-stripe-payment-plugin') . '</option>';
            echo '<option value="active" ' . selected($_REQUEST['subscription_status'], 'active', false) . '>' . __('Active', 'custom-stripe-payment-plugin') . '</option>';
            echo '<option value="inactive" ' . selected($_REQUEST['subscription_status'], 'inactive', false) . '>' . __('Inactive', 'custom-stripe-payment-plugin') . '</option>';
            echo '</select>';

            submit_button(__('Filter'), '', 'filter_action', false);
            echo '</div>';
        }
    }
}

function display_stripe_products_page() {
    global $products_list_table;

    // Include Stripe PHP library
    require_once(CUSTOM_STRIPE_PAYMENT_PLUGIN_DIR . 'vendor/autoload.php');

    // Initialize Stripe API with your secret key
    \Stripe\Stripe::setApiKey(custom_stripe_payment_plugin_get_secret_key());

    // Fetch existing Stripe products
    try {
        $products = \Stripe\Product::all(['limit' => 100]);
    } catch (Exception $e) {
        echo '<div class="error"><p>Error fetching products: ' . esc_html($e->getMessage()) . '</p></div>';
        return;
    }

    // Instantiate the list table class
    $products_list_table = new Stripe_Products_List_Table($products->data);
    $products_list_table->prepare_items();

    ?>
    <div class="wrap">
        <h1><?php echo esc_html('Stripe Products'); ?></h1>
        <form method="get">
            <input type="hidden" name="page" value="stripe-products">
            <?php
            $products_list_table->display();
            ?>
        </form>
    </div>
    <?php
}

function add_products_screen_options() {
    $screen = get_current_screen();
    if (!is_object($screen) || $screen->id != 'stripe-plugin_page_stripe-products') {
        return;
    }

    add_screen_option('per_page', [
        'label'   => __('Products per page', 'custom-stripe-payment-plugin'),
        'default' => 10,
        'option'  => 'products_per_page',
    ]);

    add_filter("manage_{$screen->id}_columns", 'stripe_products_columns');
}
add_action('admin_head', 'add_products_screen_options');

function stripe_products_columns($columns) {
    $columns['id'] = __('Product ID', 'custom-stripe-payment-plugin');
    $columns['name'] = __('Name', 'custom-stripe-payment-plugin');
    $columns['description'] = __('Description', 'custom-stripe-payment-plugin');
    $columns['price'] = __('Price (USD)', 'custom-stripe-payment-plugin');
    $columns['type'] = __('Type', 'custom-stripe-payment-plugin');
    $columns['interval'] = __('Interval', 'custom-stripe-payment-plugin');
    $columns['created'] = __('Created', 'custom-stripe-payment-plugin');
    return $columns;
}

function set_products_screen_option($status, $option, $value) {
    if ('products_per_page' === $option || 'stripe_products_hidden_columns' === $option) {
        return $value;
    }

    return $status;
}
add_filter('set-screen-option', 'set_products_screen_option', 10, 3);

function save_hidden_columns() {
    if (isset($_POST['wp_screen_options'])) {
        $screen = get_current_screen();
        if ($screen->id == 'stripe-plugin_page_stripe-products') {
            $hidden_columns = isset($_POST['hidden']) ? (array) $_POST['hidden'] : [];
            update_user_option(get_current_user_id(), "manage{$screen->id}columnshidden", $hidden_columns, true);
        }
    }
}
add_action('admin_head', 'save_hidden_columns');

function display_stripe_products_page_init() {
    global $products_list_table;

    // Include Stripe PHP library
    require_once(CUSTOM_STRIPE_PAYMENT_PLUGIN_DIR . 'vendor/autoload.php');

    // Initialize Stripe API with your secret key
    \Stripe\Stripe::setApiKey(custom_stripe_payment_plugin_get_secret_key());

    // Fetch existing Stripe products
    try {
        $products = \Stripe\Product::all(['limit' => 100]);
    } catch (Exception $e) {
        echo '<div class="error"><p>Error fetching products: ' . esc_html($e->getMessage()) . '</p></div>';
        return;
    }

    // Instantiate the list table class
    $products_list_table = new Stripe_Products_List_Table($products->data);
}
add_action('load-stripe-plugin_page_stripe-products', 'display_stripe_products_page_init');


/** Customer Page module start */
function custom_stripe_user_info_callback() {
    if (!current_user_can('manage_options')) {
        return;
    }

    try {
        // Initialize Stripe with your API key
        \Stripe\Stripe::setApiKey(custom_stripe_payment_plugin_get_secret_key());

        // Retrieve a list of customers from Stripe
        $stripe_customers = \Stripe\Customer::all(['limit' => 100]); // Adjust limit as needed
    } catch (Exception $e) {
        echo '<div class="error"><p>Error fetching customers: ' . esc_html($e->getMessage()) . '</p></div>';
        return;
    }

    // Instantiate the list table class
    $customers_list_table = new Stripe_Customers_List_Table($stripe_customers->data);
    $customers_list_table->prepare_items();

    ?>
    <div class="wrap">
        <h1><?php echo esc_html('Stripe Customers'); ?></h1>
        <form method="get">
            <input type="hidden" name="page" value="custom-stripe-user-info">
            <?php
            $customers_list_table->display();
            ?>
        </form>
    </div>
    <?php
}

function stripe_customers_columns($columns) {
    return [
        'cb'                     => '<input type="checkbox" />',
        'id'                     => __('ID', 'custom-stripe-payment-plugin'),
        'name'                   => __('Name', 'custom-stripe-payment-plugin'),
        'email'                  => __('Email', 'custom-stripe-payment-plugin'),
        'default_payment_method' => __('Default Payment Method', 'custom-stripe-payment-plugin'),
        'created'                => __('Created', 'custom-stripe-payment-plugin'),
        'description'            => __('Description', 'custom-stripe-payment-plugin'),
        'subscription_name'      => __('Subscription Name', 'custom-stripe-payment-plugin'),
        'subscription_status'    => __('Subscription Status', 'custom-stripe-payment-plugin'),
    ];
}

function add_customers_screen_options() {
    $screen = get_current_screen();
    if (!is_object($screen) || $screen->id != 'stripe-plugin_page_custom-stripe-user-info') {
        return;
    }

    // Set up screen options
    add_screen_option('per_page', [
        'label'   => __('Customers per page', 'custom-stripe-payment-plugin'),
        'default' => 10,
        'option'  => 'customers_per_page',
    ]);

    // Add custom columns
    add_filter("manage_{$screen->id}_columns", 'stripe_customers_columns');
}
add_action('admin_head', 'add_customers_screen_options');

function set_customers_screen_option($status, $option, $value) {
    if ('customers_per_page' === $option || 'stripe_customers_hidden_columns' === $option) {
        return $value;
    }

    return $status;
}
add_filter('set-screen-option', 'set_customers_screen_option', 10, 3);


function save_customers_hidden_columns() {
    if (isset($_POST['wp_screen_options'])) {
        $hidden_columns = isset($_POST['hidden']) ? (array) $_POST['hidden'] : [];
        update_user_meta(get_current_user_id(), 'stripe_customers_hidden_columns', $hidden_columns);
    }
}
add_action('admin_head', 'save_customers_hidden_columns');
