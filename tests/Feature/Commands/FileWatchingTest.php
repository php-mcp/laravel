<?php

namespace PhpMcp\Laravel\Tests\Feature\Commands;

use Illuminate\Support\Facades\Config;
use PhpMcp\Laravel\Commands\ServeCommand;
use PhpMcp\Laravel\Tests\TestCase;

class FileWatchingTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary directory for testing
        $this->tempDir = sys_get_temp_dir().'/mcp_file_watch_test_'.uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        $this->recursiveRemoveDirectory($this->tempDir);
        parent::tearDown();
    }

    public function test_watch_flag_is_registered_in_command_signature()
    {
        $command = new ServeCommand;
        $reflection = new \ReflectionClass($command);
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);

        $signature = $signatureProperty->getValue($command);
        $this->assertStringContainsString('--watch', $signature);
        $this->assertStringContainsString('Watch for file changes and automatically reload the server', $signature);
    }

    public function test_watch_flag_prevents_stdio_transport()
    {
        $this->artisan('mcp:serve --transport=stdio --watch')
            ->expectsOutputToContain('File watching is not supported with STDIO transport')
            ->assertExitCode(1);
    }

    public function test_get_watched_paths_includes_discovery_directories()
    {
        // Set up test configuration
        Config::set('mcp.discovery.base_path', $this->tempDir);
        Config::set('mcp.discovery.directories', ['app/Mcp', 'custom/handlers']);
        Config::set('mcp.discovery.definitions_file', $this->tempDir.'/routes/mcp.php');

        // Create test directories
        mkdir($this->tempDir.'/app/Mcp', 0755, true);
        mkdir($this->tempDir.'/custom/handlers', 0755, true);
        mkdir($this->tempDir.'/routes', 0755, true);

        // Create MCP definitions file
        file_put_contents($this->tempDir.'/routes/mcp.php', '<?php // MCP routes');

        $command = new ServeCommand;
        $watchedPaths = $this->invokePrivateMethod($command, 'getWatchedPaths');

        $this->assertContains($this->tempDir.'/app/Mcp', $watchedPaths);
        $this->assertContains($this->tempDir.'/custom/handlers', $watchedPaths);
        $this->assertContains($this->tempDir.'/routes', $watchedPaths);

        // The config directory path will be from base_path, not our temp directory
        // Just check that the method returns an array with some paths
        $this->assertIsArray($watchedPaths);
        $this->assertNotEmpty($watchedPaths);
    }

    public function test_get_watched_paths_handles_non_existent_directories()
    {
        Config::set('mcp.discovery.base_path', $this->tempDir);
        Config::set('mcp.discovery.directories', ['non/existent', 'also/missing']);
        Config::set('mcp.discovery.definitions_file', $this->tempDir.'/missing/mcp.php');

        $command = new ServeCommand;
        $watchedPaths = $this->invokePrivateMethod($command, 'getWatchedPaths');

        // Should not contain non-existent directories
        $this->assertNotContains($this->tempDir.'/non/existent', $watchedPaths);
        $this->assertNotContains($this->tempDir.'/also/missing', $watchedPaths);

        // Should return an array with at least some paths
        $this->assertIsArray($watchedPaths);
        $this->assertNotEmpty($watchedPaths);
    }

    public function test_get_last_modification_time_detects_php_file_changes()
    {
        // Create test PHP files
        $subDir = $this->tempDir.'/test_dir';
        mkdir($subDir, 0755, true);

        $phpFile = $subDir.'/test.php';
        $nonPhpFile = $subDir.'/test.txt';

        file_put_contents($phpFile, '<?php echo "test";');
        file_put_contents($nonPhpFile, 'not php content');

        $initialPhpTime = filemtime($phpFile);
        $initialNonPhpTime = filemtime($nonPhpFile);

        $command = new ServeCommand;
        $initialModTime = $this->invokePrivateMethod($command, 'getLastModificationTime', [[$this->tempDir]]);

        $this->assertEquals($initialPhpTime, $initialModTime);

        // Modify the non-PHP file (should not affect modification time)
        sleep(1);
        file_put_contents($nonPhpFile, 'modified non-php content');

        $afterNonPhpModTime = $this->invokePrivateMethod($command, 'getLastModificationTime', [[$this->tempDir]]);
        $this->assertEquals($initialModTime, $afterNonPhpModTime);

        // Modify the PHP file (should affect modification time)
        sleep(1);
        file_put_contents($phpFile, '<?php echo "modified";');

        $afterPhpModTime = $this->invokePrivateMethod($command, 'getLastModificationTime', [[$this->tempDir]]);
        $this->assertGreaterThan($initialModTime, $afterPhpModTime);
    }

    public function test_get_last_modification_time_handles_single_file_path()
    {
        $testFile = $this->tempDir.'/single_file.php';
        file_put_contents($testFile, '<?php echo "single file";');

        $expectedTime = filemtime($testFile);

        $command = new ServeCommand;
        $modTime = $this->invokePrivateMethod($command, 'getLastModificationTime', [[$testFile]]);

        $this->assertEquals($expectedTime, $modTime);
    }

    public function test_get_last_modification_time_handles_empty_paths()
    {
        $command = new ServeCommand;
        $modTime = $this->invokePrivateMethod($command, 'getLastModificationTime', [[]]);

        $this->assertEquals(0, $modTime);
    }

    public function test_get_last_modification_time_handles_nested_directories()
    {
        // Create nested directory structure with PHP files
        $nestedPath = $this->tempDir.'/level1/level2/level3';
        mkdir($nestedPath, 0755, true);

        $files = [
            $this->tempDir.'/level1/file1.php',
            $this->tempDir.'/level1/level2/file2.php',
            $this->tempDir.'/level1/level2/level3/file3.php',
        ];

        $latestTime = 0;
        foreach ($files as $i => $file) {
            // Create files with different timestamps
            sleep(1);
            file_put_contents($file, "<?php echo 'file {$i}';");
            $latestTime = max($latestTime, filemtime($file));
        }

        $command = new ServeCommand;
        $modTime = $this->invokePrivateMethod($command, 'getLastModificationTime', [[$this->tempDir]]);

        $this->assertEquals($latestTime, $modTime);
    }

    public function test_process_management_methods_handle_edge_cases()
    {
        $command = new ServeCommand;

        // Test isProcessRunning with various invalid inputs
        $this->assertFalse($this->invokePrivateMethod($command, 'isProcessRunning', [[]]));
        $this->assertFalse($this->invokePrivateMethod($command, 'isProcessRunning', [['process' => null]]));
        $this->assertFalse($this->invokePrivateMethod($command, 'isProcessRunning', [['process' => 'invalid']]));

        // Test stopProcess with various invalid inputs (should not throw)
        $this->invokePrivateMethod($command, 'stopProcess', [[]]);
        $this->invokePrivateMethod($command, 'stopProcess', [['process' => null]]);
        $this->invokePrivateMethod($command, 'stopProcess', [['process' => 'invalid']]);

        // If we reach here without exceptions, the test passes
        $this->assertTrue(true);
    }

    public function test_start_server_process_method_exists()
    {
        $command = new ServeCommand;
        $reflection = new \ReflectionClass($command);

        // Verify the method exists and is accessible
        $this->assertTrue($reflection->hasMethod('startServerProcess'));

        $method = $reflection->getMethod('startServerProcess');
        $this->assertTrue($method->isPrivate());
        $this->assertEquals('startServerProcess', $method->getName());
    }

    public function test_command_description_mentions_watch_functionality()
    {
        $command = new ServeCommand;
        $reflection = new \ReflectionClass($command);
        $descriptionProperty = $reflection->getProperty('description');
        $descriptionProperty->setAccessible(true);

        $description = $descriptionProperty->getValue($command);
        $this->assertStringContainsString('--watch', $description);
        $this->assertStringContainsString('automatic reloading', $description);
    }

    /**
     * Helper method to invoke private methods for testing
     */
    private function invokePrivateMethod(object $object, string $methodName, array $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    /**
     * Helper method to recursively remove directories
     */
    private function recursiveRemoveDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->recursiveRemoveDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
