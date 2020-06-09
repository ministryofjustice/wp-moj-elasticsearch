<?php
/**
 * MoJ Elasticpress plugin
 *
 * @since  0.1
 * @package wp-moj-elasticsearch
 */

namespace MOJElasticSearch;

use Aws\Firehose\FirehoseClient;
use Aws\Exception\AwsException;
use Aws\Credentials\CredentialProvider;
use Aws\Sts\StsClient;

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

    public function __construct()
    {
        parent::__construct();
        $this->hooks();
        $this->getStreamNames();
    }

    public function hooks()
    {
        add_action('admin_menu', [$this, 'pageSettings'], 1);
    }

    public function firehose()
    {
        if (!$this->client) {
            try {
                $options = $this->options();

                $profile = new InstanceProfileProvider();
                $ARN = $options['role_arn'];

                $assumeRoleCredentials = new AssumeRoleCredentialProvider([
                    'client' => new StsClient([
                        'region' => 'us-east-2',
                        'version' => '2011-06-15',
                        'credentials' => $profile
                    ]),
                    'assume_role_params' => [
                        'RoleArn' => $ARN,
                        'RoleSessionName' => $sessionName,
                    ],
                ]);

                $this->client = new FirehoseClient([
                    'version' => '2015-08-04',
                    'region' => 'eu-west-1',
                    new Credentials($options['access_key'], $options['access_secret'])
                ]);
            } catch (AwsException $e) {
                // output error message if fails
                echo $e->getMessage();
                echo "\n";
            }
        }

        return $this->client;
    }

    private function getStreamNames()
    {
        if ($this->canRun()) {
            try {
                $result = $this->firehose()->listDeliveryStreams([
                    'DeliveryStreamType' => 'DirectPut',
                ]);
                $this->updateOption('kinesis_streams', $result);
            } catch (AwsException $e) {
                // output error message if fails
                echo $e->getMessage();
                echo "\n";
            }
        }
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
                    'stream_name' => ['title' => 'Stream Name', 'callback' => [$this, 'streamName']],
                    'firehose_role' => ['title' => 'Role ARN', 'callback' => [$this, 'roleArn']],
                    'access_key' => ['title' => 'Access Key', 'callback' => [$this, 'accessKey']],
                    'access_secret' => ['title' => 'Access Secret', 'callback' => [$this, 'accessSecret']],
                    'access_keys_unlock' => ['title' => 'Key Protection', 'callback' => [$this, 'accessLock']]
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

        $this->createSections($group);
    }

    public function kinesisIntro()
    {
        $heading = __('Enter the connection details for Kinesis Data Firehose', $this->text_domain);
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
        $description = __('A stream name.', $this->text_domain);

        $output = '<p>Currently there are no streams available. This would suggest a connection to Kinesis is lost.<br>
            Please make sure your connection <strong>key</strong> and <strong>secret</strong> are correct.</p>';

        if (is_array($options['kinesis_streams'])) {
            $output = '<select name="' . $this->optionName() . '[kinesis_streams]">
                        <option value="" disabled="disabled">Choose a stream</option>';
            foreach ($options['kinesis_streams'] as $stream) {
                $output .= "<option value='" . $stream . "' " . selected($options['kinesis_streams'], $stream) . ">" .
                    $stream . "</option>";
            }
            $output = '</select><p>' . $description . '</p>';
        }

        echo $output;
    }

    public function roleArn()
    {
        $options = $this->options();
        $description = __('The Firehose role ARN', $this->text_domain);
        ?>
        <input type="text" value="<?= $options['role_arn'] ?? '' ?>"
               name='<?= $this->optionName() ?>[role_arn]'>
        <p><?= $description ?></p>
        <?php
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
            'Enter the phrase "<strong class="red">update keys</strong>" to access the Kinesis key and secret.',
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
        $type = 'text';

        if ($this->keysLocked()) {
            $readonly = ' readonly="readonly"';
            $type = 'password';
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
}
