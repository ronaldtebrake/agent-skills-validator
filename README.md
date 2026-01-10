# Agent Skills Validator

A PHP library for validating Agent Skills `SKILL.md` files according to the [agentskills.io specification](https://agentskills.io/specification).

Based on [validator.py](https://github.com/agentskills/agentskills/blob/main/skills-ref/src/skills_ref/validator.py)

## Installation

Install via Composer:

```bash
composer require ronaldtebrake/agent-skills-validator
```

## Requirements

- PHP 8.1 or higher
- Symfony Filesystem component (^6.0 || ^7.0)

## Usage

```php
use AgentSkills\Validator\Validator;

$validator = new Validator();
$result = $validator->validateSkill('/path/to/skill/directory');

if ($result['valid']) {
    echo "Skill is valid!\n";
    print_r($result['metadata']);
} else {
    echo "Validation errors:\n";
    foreach ($result['errors'] as $error) {
        echo "  - $error\n";
    }
}
```

## Return Format

The `validateSkill()` method returns an array with the following structure:

```php
[
    'valid' => bool,        // Whether the skill passed validation
    'errors' => string[],   // Array of error messages (empty if valid)
    'metadata' => array     // Parsed frontmatter metadata
]
```

## Validation Rules

The validator enforces the following rules per the Agent Skills specification:

### Required Fields

- **name**: Must be a non-empty string, lowercase, max 64 characters, containing only letters, digits, and hyphens. Cannot start/end with hyphens or contain consecutive hyphens. Must match the directory name.
- **description**: Must be a non-empty string, max 1024 characters.

### Optional Fields

- **license**: Any string value
- **allowed-tools**: Array of tool names
- **metadata**: Object with arbitrary key-value pairs
- **compatibility**: String, max 500 characters

### File Structure

- The skill directory must contain a `SKILL.md` file
- The file must have valid YAML frontmatter delimited by `---`
- Only the allowed fields listed above may be present in the frontmatter

## Example SKILL.md

```yaml
---
name: my-awesome-skill
description: A skill that does something awesome
license: MIT
compatibility: Requires PHP 8.1+
metadata:
  author: John Doe
  version: "1.0.0"
---
# My Awesome Skill

This skill provides awesome functionality for AI agents.
```

## CLI Tool for CI/CD

The package includes a CLI tool for validating skills in CI/CD environments. When installed via Composer, the binary is available at `vendor/bin/agent-skills-validator`:

```bash
# Validate all skills in a directory (automatically discovers subdirectories with SKILL.md)
vendor/bin/agent-skills-validator .claude/skills

# When running from the repository directly
php bin/agent-skills-validator .claude/skills

# If no path is provided, it defaults to the current directory
vendor/bin/agent-skills-validator
```

**Directory Discovery**: If you provide a directory path that doesn't contain a `SKILL.md` file, the tool will automatically search for all subdirectories containing `SKILL.md` files and validate them. If a directory already contains `SKILL.md`, it will be validated directly.

The CLI tool exits with code 0 on success and 1 if validation fails, making it suitable for CI/CD pipelines.

### GitHub Actions Example

Create `.github/workflows/validate-skills.yml`:

```yaml
name: Validate Agent Skills

on:
  pull_request:
    paths:
      - '**/SKILL.md'
      - '**/*/SKILL.md'

jobs:
  validate:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      
      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist
      
      - name: Validate skills
        run: vendor/bin/agent-skills-validator .claude/skills
```

## Testing

Run the test suite:

```bash
composer install
vendor/bin/phpunit
```

## Specification Reference

This validator implements the validation rules from the [Agent Skills specification](https://agentskills.io/specification), based on the reference implementation at [agentskills/agentskills](https://github.com/agentskills/agentskills/blob/main/skills-ref/src/skills_ref/validator.py).

## License

MIT License - see [LICENSE](LICENSE) file for details.
