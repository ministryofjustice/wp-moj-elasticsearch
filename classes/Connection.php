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
use Aws\Credentials\Credentials;

/**
 * Manages connections and data flow with AWS Kinesis
 * Class Connection
 * @package MOJElasticSearch
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
        $this->connect();
        $this->getStreamNames();
    }

    public function hooks()
    {
        add_action('admin_menu', [$this, 'pageSettings'], 1);
        add_action('init', [$this, 'connect']);
    }

    public function connect()
    {
        /*if (!$this->client) {
            try {
                $this->options();
                $this->client = new FirehoseClient([
                    'version' => '2015-08-04',
                    'region' => 'eu-west-1',
                    new Credentials(env('AWS_ACCESS_KEY_ID'), env('AWS_SECRET_ACCESS_KEY'))
                ]);
            } catch (AwsException $e) {
                // output error message if fails
                echo $e->getMessage();
                echo "\n";
            }
        }*/
    }

    public function getStreamNames()
    {
        if ($this->canRun()) {
            try {
                $result = $this->client->listDeliveryStreams([
                    'DeliveryStreamType' => 'DirectPut',
                ]);
                Debug::this('getStreamNames', $result, true);
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
                    'access_key' => ['title' => 'Aws Key', 'callback' => [$this, 'accessKey']],
                    'access_secret' => ['title' => 'AWS Secret', 'callback' => [$this, 'accessSecret']]
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

        if (is_array($options['kinesis_streams'])) :
            ?>
            <select name='<?= $this->optionName() ?>[kinesis_streams]'>
                <option value='' disabled="disabled">Choose a stream</option>
                <?php
                foreach ($options['kinesis_streams'] as $stream) {
                    echo "<option value='" . $stream . "' " . selected($options['kinesis_streams'], $stream) . ">" . $stream . "</option>";
                }
                ?>
            </select>
            <p><?= $description ?></p>
            <?php
        else :
            ?><p>Currently there are no streams available. This would suggest a connection to Kinesis is lost.<br>
            Please make sure your connection <strong>key</strong> and <strong>secret</strong> are correct.</p>
            <?php
        endif;
    }

    public function accessKey()
    {
        $options = $this->options();
        $description = __('A key to access Kinesis', $this->text_domain);
        ?>
        <input type="password" value="<?= $options['access_key'] ?? '' ?>" name='<?= $this->optionName() ?>[access_key]'>
        <p><?= $description ?></p>
        <?php
    }

    public function accessSecret()
    {
        $options = $this->options();
        $description = __('A secret to access Kinesis', $this->text_domain);
        ?>
        <input type="password" value="<?= $options['access_secret'] ?? '' ?>" name='<?= $this->optionName() ?>[access_secret]'>
        <p><?= $description ?></p>
        <?php
    }

    public function postsPerIndex()
    {
        $options = $this->options();
        $description = __('How many posts should we send at once? One is the default.', $this->text_domain);
        ?>
        <input type="number" value="<?= $options['index_per_post'] ?? '' ?>" name='<?= $this->optionName() ?>[index_per_post]'>
        <p><?= $description ?></p>
        <?php
    }

    public function indexButton()
    {
        $options = $this->options();
        $description = __('You won\'t be asked to confirm. Please use this button with due consideration.', $this->text_domain);
        ?>
        <button name='<?= $this->optionName() ?>[index_button]' class="button-primary">
            Destroy index and refresh
        </button>
        <p><?= $description ?></p>
        <?php
    }

    private function canRun()
    {
        return $this->client;
    }
}