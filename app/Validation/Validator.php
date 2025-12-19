<?php
/**
 * Validator Class
 * Restaurant POS System
 */

namespace App\Validation;

class Validator
{
    protected $data;
    protected $rules;
    protected $errors = [];
    protected $customMessages = [];

    public function __construct($data, $rules, $customMessages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->customMessages = $customMessages;
    }

    /**
     * Validate the data
     */
    public function validate()
    {
        $this->errors = [];

        foreach ($this->rules as $field => $fieldRules) {
            $rules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $this->validateField($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    /**
     * Validate a single field
     */
    protected function validateField($field, $value, $rule)
    {
        $parts = explode(':', $rule);
        $ruleName = $parts[0];
        $parameter = $parts[1] ?? null;

        $method = 'validate' . ucfirst($ruleName);
        if (method_exists($this, $method)) {
            $this->$method($field, $value, $parameter);
        } else {
            // Unknown rule - treat as passed
        }
    }

    /**
     * Required validation
     */
    protected function validateRequired($field, $value, $parameter)
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, 'required', 'حقل مطلوب');
        }
    }

    /**
     * Email validation
     */
    protected function validateEmail($field, $value, $parameter)
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email', 'يجب أن يكون بريد إلكتروني صحيح');
        }
    }

    /**
     * Min length validation
     */
    protected function validateMin($field, $value, $parameter)
    {
        if ($value && strlen($value) < (int)$parameter) {
            $this->addError($field, 'min', "الحد الأدنى {$parameter} أحرف");
        }
    }

    /**
     * Max length validation
     */
    protected function validateMax($field, $value, $parameter)
    {
        if ($value && strlen($value) > (int)$parameter) {
            $this->addError($field, 'max', "الحد الأقصى {$parameter} أحرف");
        }
    }

    /**
     * Integer validation
     */
    protected function validateInteger($field, $value, $parameter)
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, 'integer', 'يجب أن يكون رقم صحيح');
        }
    }

    /**
     * Numeric validation
     */
    protected function validateNumeric($field, $value, $parameter)
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->addError($field, 'numeric', 'يجب أن يكون رقم');
        }
    }

    /**
     * Min numeric validation
     */
    protected function validateMinNumeric($field, $value, $parameter)
    {
        if ($value !== null && $value !== '' && is_numeric($value) && (float)$value < (float)$parameter) {
            $this->addError($field, 'min_numeric', "يجب أن يكون أكبر من أو يساوي {$parameter}");
        }
    }

    /**
     * Max numeric validation
     */
    protected function validateMaxNumeric($field, $value, $parameter)
    {
        if ($value !== null && $value !== '' && is_numeric($value) && (float)$value > (float)$parameter) {
            $this->addError($field, 'max_numeric', "يجب أن يكون أقل من أو يساوي {$parameter}");
        }
    }

    /**
     * In validation (enum)
     */
    protected function validateIn($field, $value, $parameter)
    {
        if ($value !== null && $value !== '') {
            $allowedValues = explode(',', $parameter);
            if (!in_array($value, $allowedValues)) {
                $this->addError($field, 'in', "القيمة يجب أن تكون واحدة من: " . implode(', ', $allowedValues));
            }
        }
    }

    /**
     * URL validation
     */
    protected function validateUrl($field, $value, $parameter)
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'url', 'يجب أن يكون رابط صحيح');
        }
    }

    /**
     * Date validation
     */
    protected function validateDate($field, $value, $parameter)
    {
        if ($value && !strtotime($value)) {
            $this->addError($field, 'date', 'يجب أن يكون تاريخ صحيح');
        }
    }

    /**
     * Alpha validation (letters only)
     */
    protected function validateAlpha($field, $value, $parameter)
    {
        if ($value && !preg_match('/^[a-zA-Z]+$/', $value)) {
            $this->addError($field, 'alpha', 'يجب أن يحتوي على أحرف فقط');
        }
    }

    /**
     * Alpha numeric validation
     */
    protected function validateAlphaNum($field, $value, $parameter)
    {
        if ($value && !preg_match('/^[a-zA-Z0-9]+$/', $value)) {
            $this->addError($field, 'alpha_num', 'يجب أن يحتوي على أحرف وأرقام فقط');
        }
    }

    /**
     * Phone validation (basic)
     */
    protected function validatePhone($field, $value, $parameter)
    {
        if ($value && !preg_match('/^[\+]?[0-9\-\(\)\s]+$/', $value)) {
            $this->addError($field, 'phone', 'يجب أن يكون رقم هاتف صحيح');
        }
    }

    /**
     * File validation
     */
    protected function validateFile($field, $value, $parameter)
    {
        if (!isset($value['error']) || $value['error'] !== UPLOAD_ERR_OK) {
            $this->addError($field, 'file', 'يجب أن يكون ملف صحيح');
        }
    }

    /**
     * File size validation
     */
    protected function validateMaxFileSize($field, $value, $parameter)
    {
        if (isset($value['size']) && $value['size'] > (int)$parameter) {
            $sizeMB = round((int)$parameter / (1024 * 1024), 1);
            $this->addError($field, 'max_file_size', "حجم الملف يجب أن يكون أقل من {$sizeMB} ميجابايت");
        }
    }

    /**
     * File type validation
     */
    protected function validateMimes($field, $value, $parameter)
    {
        if (isset($value['type']) && $value['type']) {
            $allowedTypes = explode(',', $parameter);
            if (!in_array($value['type'], $allowedTypes)) {
                $this->addError($field, 'mimes', 'نوع الملف غير مدعوم');
            }
        }
    }

    /**
     * Image validation
     */
    protected function validateImage($field, $value, $parameter)
    {
        if (isset($value['type']) && $value['type']) {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($value['type'], $allowedTypes)) {
                $this->addError($field, 'image', 'يجب أن يكون صورة صحيحة (JPG, PNG, GIF, WebP)');
            }
        }
    }

    /**
     * Unique validation (for database fields)
     */
    protected function validateUnique($field, $value, $parameter)
    {
        // This would need database access - placeholder implementation
        // In real implementation, you'd check against database
        if ($value) {
            // Database uniqueness check would go here
            // For now, we'll just pass
        }
    }

    /**
     * Add error message
     */
    protected function addError($field, $rule, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Get validation errors
     */
    public function getErrors()
    {
        $flatErrors = [];
        foreach ($this->errors as $field => $fieldErrors) {
            $flatErrors = array_merge($flatErrors, $fieldErrors);
        }
        return $flatErrors;
    }

    /**
     * Get errors by field
     */
    public function getFieldErrors($field)
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Check if field has errors
     */
    public function hasError($field)
    {
        return isset($this->errors[$field]);
    }

    /**
     * Get first error for a field
     */
    public function getFirstError($field)
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Check if validation passes
     */
    public function passes()
    {
        return empty($this->errors);
    }

    /**
     * Check if validation fails
     */
    public function fails()
    {
        return !empty($this->errors);
    }

    /**
     * Add custom validation rule
     */
    public function addRule($name, callable $callback, $message)
    {
        $method = 'validate' . ucfirst($name);
        if (!method_exists($this, $method)) {
            $this->$method = $callback;
            // This would require more sophisticated implementation
            // For now, this is a placeholder
        }
    }

    /**
     * Validate array data
     */
    public function validateArray($field, $value)
    {
        if (!is_array($value)) {
            $this->addError($field, 'array', 'يجب أن يكون مصفوفة');
        }
    }

    /**
     * Validate array minimum items
     */
    protected function validateMinItems($field, $value, $parameter)
    {
        if (is_array($value) && count($value) < (int)$parameter) {
            $this->addError($field, 'min_items', "يجب أن يحتوي على {$parameter} عناصر على الأقل");
        }
    }

    /**
     * Validate array maximum items
     */
    protected function validateMaxItems($field, $value, $parameter)
    {
        if (is_array($value) && count($value) > (int)$parameter) {
            $this->addError($field, 'max_items', "يجب أن يحتوي على {$parameter} عناصر كحد أقصى");
        }
    }

    /**
     * Format error messages
     */
    public function formatErrors($separator = '<br>')
    {
        return implode($separator, $this->getErrors());
    }

    /**
     * Get errors as JSON
     */
    public function toJson()
    {
        return json_encode($this->errors, JSON_UNESCAPED_UNICODE);
    }
}