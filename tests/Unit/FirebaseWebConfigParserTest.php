<?php

namespace Tests\Unit;

use App\Services\Firebase\FirebaseWebConfigParser;
use PHPUnit\Framework\TestCase;

class FirebaseWebConfigParserTest extends TestCase
{
    protected FirebaseWebConfigParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FirebaseWebConfigParser();
    }

    public function test_parses_standard_firebase_javascript_snippet(): void
    {
        $snippet = <<<JS
// Import the functions you need from the SDKs you need
import { initializeApp } from "firebase/app";
// TODO: Add SDKs for Firebase products that you want to use
// https://firebase.google.com/docs/web/setup#available-libraries

// Your web app's Firebase configuration
const firebaseConfig = {
  apiKey: "AIzaSyB47mCKpTTJ8vl6yY1gppEhV84NRycm8gE",
  authDomain: "vyapari-darbaar.firebaseapp.com",
  projectId: "vyapari-darbaar",
  storageBucket: "vyapari-darbaar.firebasestorage.app",
  messagingSenderId: "449566278614",
  appId: "1:449566278614:web:f4e39959a5121a2e7b5a77"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);
JS;

        $result = $this->parser->parse($snippet);

        $this->assertEquals('AIzaSyB47mCKpTTJ8vl6yY1gppEhV84NRycm8gE', $result['api_key']);
        $this->assertEquals('vyapari-darbaar.firebaseapp.com', $result['auth_domain']);
        $this->assertEquals('vyapari-darbaar', $result['project_id']);
        $this->assertEquals('vyapari-darbaar.firebasestorage.app', $result['storage_bucket']);
        $this->assertEquals('449566278614', $result['messaging_sender_id']);
        $this->assertEquals('1:449566278614:web:f4e39959a5121a2e7b5a77', $result['app_id']);
    }

    public function test_parses_javascript_snippet_with_single_quotes_and_unquoted_number_sender_id(): void
    {
        $snippet = <<<JS
var firebaseConfig = {
  apiKey: 'AIzaSySingleQuoteKey123',
  authDomain: 'vyapari-darbaar.firebaseapp.com',
  projectId: 'vyapari-darbaar',
  storageBucket: 'vyapari-darbaar.appspot.com',
  messagingSenderId: 449566278614,
  appId: '1:449566278614:web:singlequote'
};
JS;

        $result = $this->parser->parse($snippet);

        $this->assertEquals('AIzaSySingleQuoteKey123', $result['api_key']);
        $this->assertEquals('vyapari-darbaar.firebaseapp.com', $result['auth_domain']);
        $this->assertEquals('vyapari-darbaar', $result['project_id']);
        $this->assertEquals('vyapari-darbaar.appspot.com', $result['storage_bucket']);
        $this->assertEquals('449566278614', $result['messaging_sender_id']);
        $this->assertEquals('1:449566278614:web:singlequote', $result['app_id']);
    }

    public function test_parses_json_string(): void
    {
        $json = json_encode([
            'apiKey' => 'AIzaSyJsonKey',
            'authDomain' => 'json-test.firebaseapp.com',
            'projectId' => 'json-test',
            'storageBucket' => 'json-test.appspot.com',
            'messagingSenderId' => '987654321',
            'appId' => '1:987654321:web:jsonapp',
        ]);

        $result = $this->parser->parse($json);

        $this->assertEquals('AIzaSyJsonKey', $result['api_key']);
        $this->assertEquals('json-test.firebaseapp.com', $result['auth_domain']);
        $this->assertEquals('json-test', $result['project_id']);
        $this->assertEquals('json-test.appspot.com', $result['storage_bucket']);
        $this->assertEquals('987654321', $result['messaging_sender_id']);
        $this->assertEquals('1:987654321:web:jsonapp', $result['app_id']);
    }

    public function test_parses_associative_array_with_camel_case_keys(): void
    {
        $array = [
            'apiKey' => 'AIzaSyArrayKey',
            'authDomain' => 'array-test.firebaseapp.com',
            'projectId' => 'array-test',
            'storageBucket' => null,
            'messagingSenderId' => '1122334455',
            'appId' => '1:1122334455:web:arrayapp',
        ];

        $result = $this->parser->parse($array);

        $this->assertEquals('AIzaSyArrayKey', $result['api_key']);
        $this->assertEquals('array-test.firebaseapp.com', $result['auth_domain']);
        $this->assertEquals('array-test', $result['project_id']);
        $this->assertNull($result['storage_bucket']);
        $this->assertEquals('1122334455', $result['messaging_sender_id']);
        $this->assertEquals('1:1122334455:web:arrayapp', $result['app_id']);
    }

    public function test_parses_associative_array_with_snake_case_keys(): void
    {
        $array = [
            'api_key' => 'AIzaSySnakeKey',
            'auth_domain' => 'snake-test.firebaseapp.com',
            'project_id' => 'snake-test',
            'storage_bucket' => 'snake-test.appspot.com',
            'messaging_sender_id' => '9988776655',
            'app_id' => '1:9988776655:web:snakeapp',
        ];

        $result = $this->parser->parse($array);

        $this->assertEquals('AIzaSySnakeKey', $result['api_key']);
        $this->assertEquals('snake-test.firebaseapp.com', $result['auth_domain']);
        $this->assertEquals('snake-test', $result['project_id']);
        $this->assertEquals('snake-test.appspot.com', $result['storage_bucket']);
        $this->assertEquals('9988776655', $result['messaging_sender_id']);
        $this->assertEquals('1:9988776655:web:snakeapp', $result['app_id']);
    }

    public function test_handles_empty_or_unrelated_text_safely(): void
    {
        $result1 = $this->parser->parse('');
        $this->assertNull($result1['api_key']);
        $this->assertNull($result1['project_id']);

        $result2 = $this->parser->parse('console.log("hello world"); function test() { return 42; }');
        $this->assertNull($result2['api_key']);
        $this->assertNull($result2['project_id']);
    }
}
