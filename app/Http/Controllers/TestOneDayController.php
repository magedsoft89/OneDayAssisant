<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Traits\ApiResponse;
use App\Mail\SendMail;
use Illuminate\Support\Facades\Mail;

class TestOneDayController extends Controller
{
    use ApiResponse;

    public function test_api()
    {

        $apis_file = file_get_contents('ApiTest.json');
        $object_file = json_decode($apis_file);
        $apis = $object_file->Api;
        foreach ($apis as $api) {
            $url = $api->url;
            $response = $this->getResponse($url);
            if ($response->getStatusCode() == 200) {
                $this->sendMail($url);
            }
            sleep(1);
        }

        return $response;
    }

    public function getResponse(string $url)
    {
        $client = new Client();
        $params['headers'] = ['Content-Type' => 'application/json'];
        $res = $client->request('GET', $url);
        return $res;
    }


    public function sendMail($api_name)
    {
        $title = "Very Important From Health Check For OneDay Server";
        $content = "There Are problems in Api " . $api_name;
        $name = "majed";
        $email = "majed.hlis.89@gmail.com";

        $details = [
            'title' => $title,
            'content' => $content,
            'username' => $name
        ];

        $subject = "Very Important From Health Check For OneDay Server";
        $view = 'emails.errors_email_template';

        Mail::to($email)->send(new SendMail($details, $view, $subject));
    }


}
