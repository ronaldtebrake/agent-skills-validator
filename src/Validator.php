<?php

namespace AgentSkills\Validator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

/**
 * Validates Agent Skills SKILL.md files per agentskills.io specification.
 *
 * See https://github.com/agentskills/agentskills/blob/main/skills-ref/src/skills_ref/validator.py.
 */
class Validator {
  /**
   * Required SKILL.md filename.
   */
  private const SKILL_MD_FILENAME = 'SKILL.md';

  /**
   * Maximum skill name length.
   */
  private const MAX_SKILL_NAME_LENGTH = 64;

  /**
   * Maximum description length.
   */
  private const MAX_DESCRIPTION_LENGTH = 1024;

  /**
   * Maximum compatibility length.
   */
  private const MAX_COMPATIBILITY_LENGTH = 500;

  /**
   * Allowed frontmatter fields per Agent Skills Spec.
   */
  private const ALLOWED_FIELDS = [
    'name',
    'description',
    'license',
    'allowed-tools',
    'metadata',
    'compatibility',
  ];

  /**
   * Validate a skill directory.
   *
   * @param string $skillPath
   *   Path to the skill directory.
   *
   * @return array
   *   Array with 'valid' => bool and 'errors' => array of error messages.
   */
  public function validateSkill(string $skillPath): array {
    $errors = [];

    if (!is_dir($skillPath)) {
      return [
        'valid' => FALSE,
        'errors' => ['Skill path is not a directory: ' . $skillPath],
      ];
    }

    $skillMdPath = $skillPath . '/' . self::SKILL_MD_FILENAME;

    $filesystem = new Filesystem();
    if (!$filesystem->exists($skillMdPath)) {
      return [
        'valid' => FALSE,
        'errors' => ['SKILL.md file not found in: ' . $skillPath],
      ];
    }

    // Use readFile() if available (Symfony 7.1+), otherwise fall back to file_get_contents()
    if (method_exists($filesystem, 'readFile')) {
      try {
        $content = $filesystem->readFile($skillMdPath);
      }
      catch (IOExceptionInterface $e) {
        return [
          'valid' => FALSE,
          'errors' => ['Cannot read SKILL.md file: ' . $skillMdPath . ' - ' . $e->getMessage()],
        ];
      }
    }
    else {
      $content = @file_get_contents($skillMdPath);
      if ($content === FALSE) {
        return [
          'valid' => FALSE,
          'errors' => ['Cannot read SKILL.md file: ' . $skillMdPath],
        ];
      }
    }

    // Parse frontmatter.
    $frontmatter = $this->parseFrontmatter($content);

    if ($frontmatter === NULL) {
      return [
        'valid' => FALSE,
        'errors' => ['Invalid YAML frontmatter in SKILL.md'],
      ];
    }

    // Validate metadata fields (check for unexpected fields first)
    $errors = array_merge($errors, $this->validateMetadataFields($frontmatter));

    // Validate required fields.
    if (!isset($frontmatter['name'])) {
      $errors[] = "Missing required field in frontmatter: name";
    }
    else {
      $errors = array_merge($errors, $this->validateName($frontmatter['name'], $skillPath));
    }

    if (!isset($frontmatter['description'])) {
      $errors[] = "Missing required field in frontmatter: description";
    }
    else {
      $errors = array_merge($errors, $this->validateDescription($frontmatter['description']));
    }

    // Validate optional fields.
    if (isset($frontmatter['compatibility'])) {
      $errors = array_merge($errors, $this->validateCompatibility($frontmatter['compatibility']));
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors,
      'metadata' => $frontmatter,
    ];
  }

  /**
   * Parse YAML frontmatter from SKILL.md content.
   *
   * @param string $content
   *   The file content.
   *
   * @return array|null
   *   Parsed frontmatter array or null if invalid.
   */
  private function parseFrontmatter(string $content): ?array {
    // Check for YAML frontmatter delimiters
    // Make trailing newline optional to handle cases where YAML ends at EOF.
    if (!preg_match('/^---\s*\n(.*?)\n---\s*(?:\n|$)/s', $content, $matches)) {
      return NULL;
    }

    $yamlContent = $matches[1];

    // Simple YAML parsing (basic implementation)
    // For production, consider using symfony/yaml component.
    $frontmatter = [];
    $lines = explode("\n", $yamlContent);
    $currentKey = NULL;
    $currentValue = '';
    $inArray = FALSE;
    $arrayKey = NULL;

    foreach ($lines as $line) {
      $line = rtrim($line);

      // Skip empty lines.
      if (empty($line)) {
        continue;
      }

      // Check for key-value pair.
      if (preg_match('/^([a-z-]+):\s*(.*)$/i', $line, $keyMatch)) {
        // Save previous value if any (unless it's already an array/object)
        if ($currentKey !== NULL && !isset($frontmatter[$currentKey])) {
          $frontmatter[$currentKey] = $this->normalizeValue($currentValue);
        }

        $currentKey = $keyMatch[1];
        $currentValue = trim($keyMatch[2]);

        // Check if it's an array start.
        if ($currentValue === '') {
          // Empty value means it's an array or object - initialize it.
          $frontmatter[$currentKey] = [];
          $inArray = FALSE;
          $arrayKey = NULL;
        }
        elseif (preg_match('/^\[(.*)\]$/', $currentValue, $arrayMatch)) {
          // Simple array format: [item1, item2].
          $items = array_map('trim', explode(',', $arrayMatch[1]));
          $frontmatter[$currentKey] = array_filter($items);
          $currentKey = NULL;
          $currentValue = '';
        }
      }
      elseif ($currentKey !== NULL) {
        // Continuation of previous value.
        if (preg_match('/^\s+-\s+(.*)$/', $line, $arrayMatch)) {
          // Array item.
          if (!isset($frontmatter[$currentKey]) || !is_array($frontmatter[$currentKey])) {
            $frontmatter[$currentKey] = [];
          }
          $frontmatter[$currentKey][] = $this->normalizeValue(trim($arrayMatch[1]));
        }
        elseif (preg_match('/^\s+([a-z-]+):\s*(.*)$/i', $line, $nestedMatch)) {
          // Nested object (like metadata)
          if (!isset($frontmatter[$currentKey]) || !is_array($frontmatter[$currentKey])) {
            $frontmatter[$currentKey] = [];
          }
          $nestedValue = trim($nestedMatch[2]);
          $frontmatter[$currentKey][$nestedMatch[1]] = $this->normalizeValue($nestedValue);
        }
        else {
          // Continuation of string value.
          $currentValue .= ' ' . trim($line);
        }
      }
    }

    // Save last value (if not already saved as array/object)
    if ($currentKey !== NULL && !isset($frontmatter[$currentKey])) {
      $frontmatter[$currentKey] = $this->normalizeValue($currentValue);
    }

    return $frontmatter;
  }

  /**
   * Normalize a YAML value.
   *
   * @param string $value
   *   The raw value string.
   *
   * @return mixed
   *   Normalized value.
   */
  private function normalizeValue(string $value) {
    $value = trim($value);

    // Remove quotes if present.
    if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
          (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
      $value = substr($value, 1, -1);
    }

    // Boolean values.
    if ($value === 'true') {
      return TRUE;
    }
    if ($value === 'false') {
      return FALSE;
    }

    return $value;
  }

  /**
   * Validate that only allowed fields are present.
   *
   * @param array $frontmatter
   *   Parsed frontmatter.
   *
   * @return array
   *   Array of error messages.
   */
  private function validateMetadataFields(array $frontmatter): array {
    $errors = [];

    $extraFields = array_diff(array_keys($frontmatter), self::ALLOWED_FIELDS);
    if (!empty($extraFields)) {
      sort($extraFields);
      $allowedFieldsSorted = self::ALLOWED_FIELDS;
      sort($allowedFieldsSorted);
      $errors[] = sprintf(
            "Unexpected fields in frontmatter: %s. Only %s are allowed.",
            implode(', ', $extraFields),
            implode(', ', $allowedFieldsSorted)
        );
    }

    return $errors;
  }

  /**
   * Validate the 'name' field.
   *
   * Skill names support Unicode letters plus hyphens.
   * Names must be lowercase and cannot start/end with hyphens.
   *
   * @param mixed $name
   *   The name value from frontmatter.
   * @param string $skillPath
   *   Path to skill directory.
   *
   * @return array
   *   Array of error messages.
   */
  private function validateName($name, string $skillPath): array {
    $errors = [];

    // Must be a non-empty string.
    if (!is_string($name) || trim($name) === '') {
      $errors[] = "Field 'name' must be a non-empty string";
      return $errors;
    }

    // Normalize (trim and normalize Unicode - use NFKC if intl extension is available)
    $name = trim($name);
    // Use Normalizer if available (intl extension), otherwise just use the string as-is.
    if (extension_loaded('intl') && class_exists('Normalizer')) {
      $name = \Normalizer::normalize($name, \Normalizer::FORM_KC);
    }

    // Check length.
    $nameLength = mb_strlen($name, 'UTF-8');
    if ($nameLength > self::MAX_SKILL_NAME_LENGTH) {
      $errors[] = sprintf(
            "Skill name '%s' exceeds %d character limit (%d chars)",
            $name,
            self::MAX_SKILL_NAME_LENGTH,
            $nameLength
        );
    }

    // Must be lowercase.
    if ($name !== mb_strtolower($name, 'UTF-8')) {
      $errors[] = sprintf("Skill name '%s' must be lowercase", $name);
    }

    // Must not start or end with hyphen.
    if (substr($name, 0, 1) === '-' || substr($name, -1) === '-') {
      $errors[] = "Skill name cannot start or end with a hyphen";
    }

    // Must not contain consecutive hyphens.
    if (strpos($name, '--') !== FALSE) {
      $errors[] = "Skill name cannot contain consecutive hyphens";
    }

    // Must only contain letters, digits, and hyphens
    // Use Unicode-aware character checking.
    $nameChars = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($nameChars as $char) {
      // Check if character is a letter (\p{L}), digit (\p{N}), or hyphen.
      if (!preg_match('/^[\p{L}\p{N}-]$/u', $char)) {
        $errors[] = sprintf(
              "Skill name '%s' contains invalid characters. Only letters, digits, and hyphens are allowed.",
              $name
          );
        break;
      }
    }

    // Must match directory name.
    $dirName = basename($skillPath);
    // Normalize directory name too.
    if (extension_loaded('intl') && class_exists('Normalizer')) {
      $dirName = \Normalizer::normalize($dirName, \Normalizer::FORM_KC);
    }
    if ($dirName !== $name) {
      $errors[] = sprintf(
            "Directory name '%s' must match skill name '%s'",
            basename($skillPath),
            $name
        );
    }

    return $errors;
  }

  /**
   * Validate the 'description' field.
   *
   * @param mixed $description
   *   The description value from frontmatter.
   *
   * @return array
   *   Array of error messages.
   */
  private function validateDescription($description): array {
    $errors = [];

    // Must be a non-empty string.
    if (!is_string($description) || trim($description) === '') {
      $errors[] = "Field 'description' must be a non-empty string";
      return $errors;
    }

    $descriptionLength = mb_strlen($description, 'UTF-8');
    if ($descriptionLength > self::MAX_DESCRIPTION_LENGTH) {
      $errors[] = sprintf(
            "Description exceeds %d character limit (%d chars)",
            self::MAX_DESCRIPTION_LENGTH,
            $descriptionLength
        );
    }

    return $errors;
  }

  /**
   * Validate the 'compatibility' field.
   *
   * @param mixed $compatibility
   *   The compatibility value from frontmatter.
   *
   * @return array
   *   Array of error messages.
   */
  private function validateCompatibility($compatibility): array {
    $errors = [];

    // Must be a string.
    if (!is_string($compatibility)) {
      $errors[] = "Field 'compatibility' must be a string";
      return $errors;
    }

    $compatibilityLength = mb_strlen($compatibility, 'UTF-8');
    if ($compatibilityLength > self::MAX_COMPATIBILITY_LENGTH) {
      $errors[] = sprintf(
            "Compatibility exceeds %d character limit (%d chars)",
            self::MAX_COMPATIBILITY_LENGTH,
            $compatibilityLength
        );
    }

    return $errors;
  }

}
