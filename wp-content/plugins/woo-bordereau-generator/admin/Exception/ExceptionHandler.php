<?php
namespace WooBordereauGenerator\Admin\Exception;

class ExceptionHandler {

    protected $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    // display custom admin notice
    function error() { ?>
        <div class="notice notice-error is-dismissible">
            <p><?php _e($this->message, 'woo-bordereau-generator'); ?></p>
        </div>

    <?php }

    public function display_error() {
        add_action('admin_notices', [$this, 'error']);
    }

}
