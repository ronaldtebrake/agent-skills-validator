<?php

namespace AgentSkills\Validator\Tests;

use AgentSkills\Validator\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Validator.
 */
class ValidatorTest extends TestCase
{
    private Validator $validator;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new Validator();
        $this->tempDir = sys_get_temp_dir() . '/agent-skills-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    /**
     * Test validation of a valid skill.
     */
    public function testValidSkill(): void
    {
        $skillPath = $this->tempDir . '/test-skill';
        mkdir($skillPath, 0755, true);

        $skillMd = <<<'YAML'
---
name: test-skill
description: A test skill for validation
---
# Test Skill

This is a test skill.
YAML;

        file_put_contents($skillPath . '/SKILL.md', $skillMd);

        $result = $this->validator->validateSkill($skillPath);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals('test-skill', $result['metadata']['name']);
    }

    /**
     * Test validation fails when SKILL.md is missing.
     */
    public function testMissingSkillMd(): void
    {
        $skillPath = $this->tempDir . '/test-skill';
        mkdir($skillPath, 0755, true);

        $result = $this->validator->validateSkill($skillPath);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('SKILL.md', implode(' ', $result['errors']));
    }

    /**
     * Test validation fails when name doesn't match directory.
     */
    public function testNameMismatch(): void
    {
        $skillPath = $this->tempDir . '/test-skill';
        mkdir($skillPath, 0755, true);

        $skillMd = <<<'YAML'
---
name: different-name
description: A test skill
---
YAML;

        file_put_contents($skillPath . '/SKILL.md', $skillMd);

        $result = $this->validator->validateSkill($skillPath);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $errorString = implode(' ', $result['errors']);
        $this->assertTrue(
            strpos($errorString, 'must match') !== false || strpos($errorString, 'match directory name') !== false,
            "Expected error message about directory name mismatch, got: {$errorString}"
        );
    }

    /**
     * Test validation fails when name is invalid.
     */
    public function testInvalidName(): void
    {
        $skillPath = $this->tempDir . '/Test-Skill';
        mkdir($skillPath, 0755, true);

        $skillMd = <<<'YAML'
---
name: Test-Skill
description: A test skill
---
YAML;

        file_put_contents($skillPath . '/SKILL.md', $skillMd);

        $result = $this->validator->validateSkill($skillPath);

        // Should fail because name contains uppercase
        $this->assertFalse($result['valid']);
    }

    /**
     * Test validation fails when description is missing.
     */
    public function testMissingDescription(): void
    {
        $skillPath = $this->tempDir . '/test-skill';
        mkdir($skillPath, 0755, true);

        $skillMd = <<<'YAML'
---
name: test-skill
---
YAML;

        file_put_contents($skillPath . '/SKILL.md', $skillMd);

        $result = $this->validator->validateSkill($skillPath);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('description', implode(' ', $result['errors']));
    }

    /**
     * Test validation fails when name starts with hyphen.
     */
    public function testNameStartsWithHyphen(): void
    {
        $skillPath = $this->tempDir . '/-test-skill';
        mkdir($skillPath, 0755, true);

        $skillMd = <<<'YAML'
---
name: -test-skill
description: A test skill
---
YAML;

        file_put_contents($skillPath . '/SKILL.md', $skillMd);

        $result = $this->validator->validateSkill($skillPath);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('start or end with a hyphen', implode(' ', $result['errors']));
    }

    /**
     * Test validation fails when name ends with hyphen.
     */
    public function testNameEndsWithHyphen(): void
    {
        $skillPath = $this->tempDir . '/test-skill-';
        mkdir($skillPath, 0755, true);

        $skillMd = <<<'YAML'
---
name: test-skill-
description: A test skill
---
YAML;

        file_put_contents($skillPath . '/SKILL.md', $skillMd);

        $result = $this->validator->validateSkill($skillPath);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('start or end with a hyphen', implode(' ', $result['errors']));
    }

    /**
     * Test validation fails when name contains consecutive hyphens.
     */
    public function testNameConsecutiveHyphens(): void
    {
        $skillPath = $this->tempDir . '/test--skill';
        mkdir($skillPath, 0755, true);

        $skillMd = <<<'YAML'
---
name: test--skill
description: A test skill
---
YAML;

        file_put_contents($skillPath . '/SKILL.md', $skillMd);

        $result = $this->validator->validateSkill($skillPath);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('consecutive hyphens', implode(' ', $result['errors']));
    }

    /**
     * Test validation fails when name is too long.
     */
    public function testNameTooLong(): void
    {
        $longName = str_repeat('a', 65);
        $skillPath = $this->tempDir . '/' . $longName;
        mkdir($skillPath, 0755, true);

        $skillMd = <<<YAML
---
name: {$longName}
description: A test skill
---
YAML;

        file_put_contents($skillPath . '/SKILL.md', $skillMd);

        $result = $this->validator->validateSkill($skillPath);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('exceeds', implode(' ', $result['errors']));
        $this->assertStringContainsString('64', implode(' ', $result['errors']));
    }

    /**
     * Test validation fails when description is empty.
     */
    public function testDescriptionEmpty(): void
    {
        $skillPath = $this->tempDir . '/test-skill';
        mkdir($skillPath, 0755, true);

        $skillMd = <<<'YAML'
---
name: test-skill
description: 
---
YAML;

        file_put_contents($skillPath . '/SKILL.md', $skillMd);

        $result = $this->validator->validateSkill($skillPath);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('description', implode(' ', $result['errors']));
    }

    /**
     * Test validation fails when description is too long.
     */
    public function testDescriptionTooLong(): void
    {
        $skillPath = $this->tempDir . '/test-skill';
        mkdir($skillPath, 0755, true);

        $longDescription = str_repeat('a', 1025);
        $skillMd = <<<YAML
---
name: test-skill
description: {$longDescription}
---
YAML;

        file_put_contents($skillPath . '/SKILL.md', $skillMd);

        $result = $this->validator->validateSkill($skillPath);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('exceeds', implode(' ', $result['errors']));
        $this->assertStringContainsString('1024', implode(' ', $result['errors']));
    }

    /**
     * Test validation fails when compatibility is too long.
     */
    public function testCompatibilityTooLong(): void
    {
        $skillPath = $this->tempDir . '/test-skill';
        mkdir($skillPath, 0755, true);

        $longCompatibility = str_repeat('a', 501);
        $skillMd = <<<YAML
---
name: test-skill
description: A test skill
compatibility: {$longCompatibility}
---
YAML;

        file_put_contents($skillPath . '/SKILL.md', $skillMd);

        $result = $this->validator->validateSkill($skillPath);

        $this->assertFalse($result['valid']);
        $errorString = strtolower(implode(' ', $result['errors']));
        $this->assertStringContainsString('compatibility', $errorString);
        $this->assertStringContainsString('exceeds', $errorString);
        $this->assertStringContainsString('500', $errorString);
    }

    /**
     * Test validation fails when unexpected fields are present.
     */
    public function testUnexpectedFields(): void
    {
        $skillPath = $this->tempDir . '/test-skill';
        mkdir($skillPath, 0755, true);

        $skillMd = <<<'YAML'
---
name: test-skill
description: A test skill
invalid-field: should not be here
---
YAML;

        file_put_contents($skillPath . '/SKILL.md', $skillMd);

        $result = $this->validator->validateSkill($skillPath);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Unexpected fields', implode(' ', $result['errors']));
        $this->assertStringContainsString('invalid-field', implode(' ', $result['errors']));
    }

    /**
     * Test validation passes with all optional fields.
     */
    public function testValidSkillWithOptionalFields(): void
    {
        $skillPath = $this->tempDir . '/test-skill';
        mkdir($skillPath, 0755, true);

        $skillMd = <<<'YAML'
---
name: test-skill
description: A test skill
license: MIT
compatibility: Requires PHP 8.1+
metadata:
  author: test-author
  version: "1.0"
---
YAML;

        file_put_contents($skillPath . '/SKILL.md', $skillMd);

        $result = $this->validator->validateSkill($skillPath);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * Remove directory recursively.
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }
}
