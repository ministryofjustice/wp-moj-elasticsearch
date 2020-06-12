<?php
/**
 * MoJ Elasticpress plugin
 *
 * @since  0.1
 * @package wp-moj-elasticsearch
 */

namespace MOJElasticSearch;

use Aws\Exception\AwsException;
use Aws\Firehose\FirehoseClient;
use Aws\Firehose\Exception\FirehoseException;

/**
 * Manages connections and data flow with AWS Kinesis
 * Class Connection
 * @package MOJElasticSearch
 * @SuppressWarnings(PHPMD)
 */
class Connection extends Admin
{
    /**
     * This class requires settings fields in the plugins dashboard.
     * Include the Settings trait
     */
    use Settings, Debug;

    /**
     * A connection is stored here.
     * Use this variable to access the AWS Kinesis Client
     * @var null
     */
    public $client = null;

    /**
     * Flag to indicate whether the connection to Kinesis failed
     * @var bool
     */
    protected $client_failed = false;

    /**
     * Flag to indicate whether the connection to Kinesis failed
     * @var bool
     */
    protected $client_credentials = null;

    /**
     * Flag to indicate if an AWS Key and Secret is available in the environment. Is an array if they do.
     * @var bool|array
     */
    protected $aws_env = false;

    public function __construct()
    {
        parent::__construct();
        $this->firehose();
        $this->hooks();
        $this->getStreamNames();
    }

    public function hooks()
    {
        add_action('init', [$this, 'getStreamNames'], 1);
        add_action('admin_menu', [$this, 'pageSettings'], 1);
    }

    public function firehose()
    {
        if (!$this->client) {
            try {
                if (!$this->checkAwsEnvironment()) {
                    $options = $this->options();
                    if (!isset($options['access_key'])) {
                        return false;
                    }
                    $this->client_credentials = [
                        'key' => $options['access_key'],
                        'secret' => $options['access_secret'],
                    ];
                }

                $this->client = new FirehoseClient([
                    'version' => '2015-08-04',
                    'region' => 'eu-west-1',
                    'credentials' => $this->client_credentials
                ]);
            } catch (FirehoseException $e) {
                $this->client_failed = true;
            }
        }

        return $this->client;
    }

    public function getStreamNames()
    {
        if ($this->canRun()) {
            try {
                $result = $this->firehose()->listDeliveryStreams([
                    'DeliveryStreamType' => 'DirectPut'
                ]);
                $result = $result->get('DeliveryStreamNames');
                $this->updateOption('kinesis_streams', $result);
                return;
            } catch (AwsException $e) {
                $this->client_failed = true;
                return;
            }
        }

        $this->deleteOption('kinesis_streams');
    }

    /**
     * This method is quite literally a space saving settings method
     *
     * Create your tab by adding to the $tabs global array with a label as the value
     * Configure a section with fields for that tab as arrays by adding to the $sections global array.
     */
    public function pageSettings()
    {
        $group = 'kinesis';

        Admin::$tabs[$group] = 'AWS Kinesis';
        Admin::$sections[$group] = [
            [
                'id' => 'kinesis_connect',
                'title' => 'Connection',
                'callback' => [$this, 'kinesisIntro'],
                'fields' => [
                    'stream_name' => ['title' => 'Stream Name', 'callback' => [$this, 'streamName']]
                ]
            ],
            [
                'id' => 'kinesis_index',
                'title' => 'Refresh Index',
                'callback' => [$this, 'kinesisIndexIntro'],
                'fields' => [
                    'index_per_post' => ['title' => 'Posts per-index', 'callback' => [$this, 'postsPerIndex']],
                    'index_button' => ['title' => 'Index Now?', 'callback' => [$this, 'indexButton']]
                ]
            ]
        ];

        // don't confuse the interface. If AWS env vars exist don't show these fields
        if (!$this->aws_env) {
            Admin::$sections[$group][0]['fields']['access_key'] = [
                'title' => 'Access Key',
                'callback' => [$this, 'accessKey']
            ];
            Admin::$sections[$group][0]['fields']['access_secret'] = [
                'title' => 'Access Secret',
                'callback' => [$this, 'accessSecret']
            ];
            Admin::$sections[$group][0]['fields']['access_keys_unlock'] = [
                'title' => 'Key Protection',
                'callback' => [$this, 'accessLock']
            ];
        }

        $this->createSections($group);
    }

    public function kinesisIntro()
    {
        $heading = __('Enter the connection details for Kinesis Data Firehose', $this->text_domain);
        if ($this->aws_env) {
            $heading = __('Select a stream for Kinesis Data Firehose', $this->text_domain);
        }
        $description = __('', $this->text_domain);
        echo '<div class="intro"><strong>' . $heading . '</strong><br>' . $description . '</div>';
    }

    public function kinesisIndexIntro()
    {
        $heading = __('Launching a bulk index', $this->text_domain);
        $description = __('Please use with caution on a production server', $this->text_domain);
        echo '<div class="intro"><strong>' . $heading . '</strong><br>' . $description . '</div>';
    }

    public function streamName()
    {
        $options = $this->options();
        $description = __('Define the connection stream. Data will be sent here.', $this->text_domain);

        $output = '<p>Currently there are no streams available.
                        This would suggest a connection to Kinesis cannot be made.<br>
                        Please make sure your connection <strong>key</strong> and <strong>secret</strong>
                        are correct.</p>';

        if (is_array($options['kinesis_streams'])) {
            $output = '<select name="' . $this->optionName() . '[kinesis_streams]">
                        <option value="">Choose a stream</option>';
            foreach ($options['kinesis_streams'] as $key => $stream) {
                $output .= "<option value='" . $stream . "' " .
                    selected($options['kinesis_streams'][$key], $stream, false) . ">" .
                    ucwords(str_replace(['-', '_'], ' ', $stream)) . "</option>";
            }
            $output .= '</select><p>' . $description . '</p>';
        }

        echo $output;
    }

    public function accessKey()
    {
        $this->keyInput('access_key') ?>
        <p><?= __('A key to access Kinesis', $this->text_domain) ?></p>
        <?php
    }

    public function accessSecret()
    {
        $this->keyInput('access_secret') ?>
        <p><?= __('A secret to access Kinesis', $this->text_domain) ?></p>
        <?php
    }

    public function accessLock()
    {
        if (!$this->keysLocked()) {
            echo "<strong>Keys will lock on update to protect from accidental disconnect.</strong>";
            return;
        }

        $options = $this->options();
        $description = __(
            'Enter the phrase "<strong class="red">update keys</strong>" to access AWS key and secret.',
            $this->text_domain
        );
        ?>
        <input type="text" value="<?= $options['access_keys_unlock'] ?? '' ?>"
               name='<?= $this->optionName() ?>[access_keys_unlock]'>
        <p><?= $description ?></p>
        <?php
    }

    public function keyInput($key)
    {
        $value = $this->options()[$key];
        $readonly = '';
        $type = 'password';

        if ($this->keysLocked()) {
            $readonly = ' readonly="readonly"';
        }

        echo '<input
                type="' . $type . '"
                value="' . $value . '"
                name="' . $this->optionName() . '[' . $key . ']"
                class="input"' . $readonly . ' />';
    }

    public function postsPerIndex()
    {
        $options = $this->options();
        $description = __('How many posts should we send at once? One is the default.', $this->text_domain);
        ?>
        <input type="number" value="<?= $options['index_per_post'] ?? '' ?>"
               name='<?= $this->optionName() ?>[index_per_post]'>
        <p><?= $description ?></p>
        <?php
    }

    public function indexButton()
    {
        $description = __(
            'You will be asked to confirm your decision. Please use this button with due consideration.',
            $this->text_domain
        );
        ?>
        <div id="my-content-id" style="display:none;">
            <p>
                Please make sure you are aware of the implications when commanding a new index. If you are unsure, exit
                out of this box by clicking away from this modal.<br><strong>Please confirm:</strong>
            </p>
            <a class="button-primary index_pre_link"
               title="Are you sure?">
                I'm ready to refresh the index... GO!
            </a>
        </div>
        <button name='<?= $this->optionName() ?>[index_button]' class="button-primary index_button" disabled="disabled">
            Destroy index and refresh
        </button>
        <a href="#TB_inline?&width=400&height=150&inlineId=my-content-id" class="button-primary thickbox"
           title="Refresh Elasticsearch Index">
            Destroy index and refresh
        </a>
        <p><?= $description ?></p>
        <?php
    }

    private function canRun()
    {
        return $this->client;
    }

    private function keysLocked()
    {
        $options = $this->options();
        if (isset($options['access_keys_lock']) && $options['access_keys_lock'] === 'yes') {
            return true;
        }

        return false;
    }

    public function checkAwsEnvironment()
    {
        $key = getenv('AWS_ACCESS_KEY_ID');
        $secret = getenv('AWS_SECRET_ACCESS_KEY');
        if ($key && $secret) {
            $this->clearAPIKeySecret();
            return $this->aws_env = true;
        }

        return false;
    }

    public function clearAPIKeySecret()
    {
        $options = $this->options();
        unset($options['access_key']);
        unset($options['access_secret']);
    }
}
