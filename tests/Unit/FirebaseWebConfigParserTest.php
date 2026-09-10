<?php

namespace Tests\Unit;

use App\Services\FirebaseWebConfigParserService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FirebaseWebConfigParserTest extends TestCase
{
    protected FirebaseWebConfigParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FirebaseWebConfigParserService;
    }

    public function test_parses_javascript_snippet_correctly(): void
    {
        $jsSnippet = <<<JS
const firebaseConfig = {
  apiKey: "AIzaSyD-1234567890abcdef",
  authDomain: "vyapari-darbaar.firebaseapp.com",
  projectId: "vyapari-darbaar",
  storageBucket: "vyapari-darbaar.appspot.com",
  messagingSenderId: "123456789012",
  appId: "1:123456789012:web:abcdef123456"
};
JS;

        $result = $this->parser->parse($jsSnippet);

        $this->assertEquals('AIzaSyD-1234567890abcdef', $result['apiKey']);
        $this->assertEquals('vyapari-darbaar.firebaseapp.com', $result['authDomain']);
        $this->assertEquals('vyapari-darbaar', $result['projectId']);
        $this->assertEquals('vyapari-darbaar.appspot.com', $result['storageBucket']);
        $this->assertEquals('123456789012', $result['messagingSenderId']);
        $this->assertEquals('1:123456789012:web:abcdef123456', $result['appId']);
    }

    public function test_parses_json_string_correctly(): void
    {
        $json = json_encode([
            'apiKey' => 'AIzaSyD-JSON',
            'authDomain' => 'json.firebaseapp.com',
            'projectId' => 'json-project',
            'storageBucket' => 'json.appspot.com',
            'messagingSenderId' => '999888777',
            'appId' => '1:999888777:web:jsonapp',
        ]);

        $result = $this->parser->parse($json);

        $this->assertEquals('AIzaSyD-JSON', $result['apiKey']);
        $this->assertEquals('json-project', $result['projectId']);
    }

    public function test_parses_array_with_snake_case_keys(): void
    {
        $array = [
            'api_key' => 'AIzaSyD-SNAKE',
            'auth_domain' => 'snake.firebaseapp.com',
            'project_id' => 'snake-project',
            'storage_bucket' => 'snake.appspot.com',
            'messaging_sender_id' => '555444333',
            'app_id' => '1:555444333:web:snakeapp',
        ];

        $result = $this->parser->parse($array);

        $this->assertEquals('AIzaSyD-SNAKE', $result['apiKey']);
        $this->assertEquals('snake-project', $result['projectId']);
    }

    public function test_throws_exception_on_empty_or_invalid_config(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->parser->parse('');
    }

    public function test_validates_project_id_match(): void
    {
        $serviceAccount = [
            'project_id' => 'vyapari-darbaar',
            'private_key' => 'fake_key',
        ];

        $this->assertTrue($this->parser->validateProjectMatch('vyapari-darbaar', $serviceAccount));
        $this->assertFalse($this->parser->validateProjectMatch('different-project', $serviceAccount));
    }
}
