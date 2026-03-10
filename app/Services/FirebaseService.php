<?php

namespace App\Services;

use App\Models\FcmToken;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Storage;
use Kreait\Firebase\Database;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Exception;
use InvalidArgumentException;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Laravel\Firebase\Facades\Firebase;

class FirebaseService
{
    protected Storage $storage;
    protected Database $database;
    protected Messaging $messaging;

    public function __construct()
    {

        $factory = (new Factory)
            ->withServiceAccount(base_path('keys/test-1bb9e-firebase-adminsdk-fbsvc-452ae8115e.json'))
            ->withDatabaseUri(env('FIREBASE_DATABASE_URL'))
            ->withDefaultStorageBucket(env('FIREBASE_STORAGE_BUCKET'));


        $this->storage = $factory->createStorage();
        $this->database = $factory->createDatabase();
        $this->messaging = $factory->createMessaging();
    }

    // public function uploadFile($file, $path)
    // {
    //     $bucket = $this->storage->getBucket();
    //     $bucket->upload(
    //         file_get_contents($file),
    //         ['name' => $path]
    //     );

    //     return "https://storage.googleapis.com/{$bucket->name()}/{$path}";
    // }
    // public function uploadFile($file, $path)
    // {
    //     $bucket = $this->storage->getBucket();

    //     $object = $bucket->upload(
    //         fopen($file, 'r'), // or fopen($file->getRealPath(), 'r') if $file is UploadedFile
    //         [
    //             'name' => $path,
    //         ]
    //     );


    //     $object->update(['acl' => []], ['predefinedAcl' => 'PUBLICREAD']);

    //     return "https://storage.googleapis.com/{$bucket->name()}/{$path}";
    // }

     public function uploadFile($file, $path)
    {
        try {
            $bucket = $this->storage->getBucket();
            $fileStream = is_string($file) ? fopen($file, 'r') : fopen($file->getRealPath(), 'r');
            $object = $bucket->upload(
                $fileStream,
                ['name' => $path]
            );
            $object->update(['acl' => []], ['predefinedAcl' => 'PUBLICREAD']);
            return "https://storage.googleapis.com/{$bucket->name()}/{$path}";
        } catch (\Exception $e) {
            return null;
        }
    }


    public function updateLocation(string $deliveryId, array $data)
    {
        return $this->database
            ->getReference("deliveries/{$deliveryId}/location")
            ->set($data);
    }

    public function pushLocationHistory(string $deliveryId, array $data)
    {
        return $this->database
            ->getReference("deliveries/{$deliveryId}/location_history")
            ->push($data);
    }

    /**
     * @var string Andoird Notification Color
     */
    public const AND_COLOR = '#008F70';

    /**
     * To Create Firebase custom
     *
     * @param string $uid User's UUID
     * @param array $claims Custom Data of User
     */
    public static function createCustomToken($uid, $claims = []): array
    {
        try {
            $auth = Firebase::auth();
            $customToken = $auth->createCustomToken($uid, $claims);
            $customTokenString = $customToken->toString();
            return [
                'status' => true,
                'token' => $customTokenString,
                'error' => null
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'token' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * To send single token notification
     *
     * @param string $token Device Token
     * @param array{title:string, body:string, imag:string} $notify With Key title, body, & image
     * @param array{module:string, uuid:string, ...} $data If any specific Data Array
     * @return array
     */
    public static function sendSingleNotification($token, $notify, $data = [])
    {
        if (empty($notify['title'])) {
            return [
                'success' => false,
                'message' => 'Title can not be empty'
            ];
        }
        if (empty($notify['body'])) {
            return [
                'success' => false,
                'message' => 'Body can not be empty'
            ];
        }
        $notify['color'] = self::AND_COLOR;
        $androidConfig = AndroidConfig::fromArray([
            'ttl' => '3600s',
            'priority' => 'normal',
            'notification' => [
                'title' => $notify['title'] ?? null,
                'body' => $notify['body'] ?? null,
                'image' => $notify['image'] ?? null
            ],
        ]);

        $iosConfig = ApnsConfig::fromArray([
            'headers' => [
                'apns-priority' => '10',
            ],
            'payload' => [
                'aps' => [
                    'alert' => $notify,
                ],
                'mutable-content' => 1
            ],
        ]);

        $messaging = Firebase::messaging();
        $message = CloudMessage::new()
            ->withData($data)
            ->toToken($token)
            ->withAndroidConfig($androidConfig)
            ->withApnsConfig($iosConfig);


        try {
            $resp = $messaging->send($message);
            return [
                'success' => true,
                'message' => 'Message Sent!',
                'data'    => $resp
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "API Error: " . $e->getMessage()
            ];
        } catch (MessagingException $e) {
            return [
                'success' => false,
                'message' => "Messaging Error: " . $e->getMessage()
            ];
        } catch (InvalidArgumentException $e) {
            return [
                'success' => false,
                'message' => "Argument Error: " . $e->getMessage()
            ];
        } catch (FirebaseException $e) {
            return [
                'success' => false,
                'message' => "Firebase Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Send Notification to Multiple Device Tokens
     *
     * @param array $tokens Tokens Array
     * @param array $notify With Key title, body, & image
     * @param array $data If any specific Data Array
     * @return array
     */
    public static function sendMultiDeviceNotification($tokens, $notify, $data = [], $to_id = null)
    {
        if (empty($notify['title'])) {
            return [
                'success' => false,
                'message' => 'Title can not be empty'
            ];
        }
        if (empty($notify['body'])) {
            return [
                'success' => false,
                'message' => 'Body can not be empty'
            ];
        }
        // if (empty($data)) {
        //     $data = $notify;
        // }
        // $notify['data'] = $data;

        // try {

        $notify['color'] = self::AND_COLOR;
        $androidConfig = AndroidConfig::fromArray([
            'ttl' => '3600s',
            'priority' => 'normal',
            'notification' => [
                'title' => $notify['title'] ?? null,
                'body' => $notify['body'] ?? null,
                'image' => $notify['image'] ?? null
            ],
        ]);


        $iosConfig = ApnsConfig::fromArray([
            'headers' => [
                'apns-priority' => '10',
            ],
            'payload' => [
                'aps' => [
                    'alert' => $notify,
                ],
                'mutable-content' => 1
            ],
        ]);
        $messaging = Firebase::messaging();
        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::new()
                    // ->withNotification($notify)
                    ->withData($data)
                    ->toToken($token)
                    ->withAndroidConfig($androidConfig)
                    ->withApnsConfig($iosConfig);

                $resp = $messaging->send($message);
                $response[] = $resp;
            } catch (Exception $e) {

                $response[] = [
                    'success' => false,
                    'error' =>  $e->getMessage()
                ];
            }
        }
        return [
            'success' => true,
            'message' => 'Message Sent!',
        ];
    }


}
