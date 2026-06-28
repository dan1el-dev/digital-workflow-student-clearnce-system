<?php
/**
 * Request Validation Library
 * 
 * Provides centralised validation for all form inputs and business rules
 * across the student clearance system. Used by submit.php, resubmit.php,
 * officer/action.php, and admin handlers.
 *
 * Usage:
 *   $v = new RequestValidator();
 *   $v->required('field_name', $value)->max_length('field_name', $value, 500);
 *   if ($v->fails()) { /* handle errors *\/ }
 *   $errors = $v->errors();
 */

require_once __DIR__ . '/db.php';

class RequestValidator
{
    /** @var array<string, string[]> */
    private array $errors = [];

    // -------------------------------------------------------------------------
    // Error management
    // -------------------------------------------------------------------------

    private function add_error(string $field, string $message): self
    {
        $this->errors[$field][] = $message;
        return $this;
    }

    /** Returns true if any validation rule failed. */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /** Returns all error messages keyed by field name. */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Returns a flat list of all error messages (useful for die() or flash).
     */
    public function error_list(): array
    {
        $list = [];
        foreach ($this->errors as $messages) {
            foreach ($messages as $msg) {
                $list[] = $msg;
            }
        }
        return $list;
    }

    /**
     * Returns the first error message for a given field, or null.
     */
    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    // =========================================================================
    // GENERIC FIELD RULES
    // =========================================================================

    /**
     * Assert a value is not empty.
     */
    public function required(string $field, mixed $value, string $label = ''): self
    {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        if ($value === null || $value === '' || (is_array($value) && count($value) === 0)) {
            $this->add_error($field, "{$label} is required.");
        }
        return $this;
    }

    /**
     * Assert a string does not exceed a character length.
     */
    public function max_length(string $field, string $value, int $max, string $label = ''): self
    {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        if (mb_strlen($value) > $max) {
            $this->add_error($field, "{$label} must not exceed {$max} characters.");
        }
        return $this;
    }

    /**
     * Assert a string meets a minimum character length.
     */
    public function min_length(string $field, string $value, int $min, string $label = ''): self
    {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        if (mb_strlen($value) < $min) {
            $this->add_error($field, "{$label} must be at least {$min} characters.");
        }
        return $this;
    }

    /**
     * Assert a value is within a list of allowed options.
     */
    public function in_list(string $field, mixed $value, array $allowed, string $label = ''): self
    {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        if (!in_array($value, $allowed, true)) {
            $readable = implode(', ', array_map(fn($v) => "'$v'", $allowed));
            $this->add_error($field, "{$label} must be one of: {$readable}.");
        }
        return $this;
    }

    /**
     * Assert a value matches a regular expression.
     */
    public function regex(string $field, string $value, string $pattern, string $message = ''): self
    {
        if (!preg_match($pattern, $value)) {
            $this->add_error($field, $message ?: "The {$field} format is invalid.");
        }
        return $this;
    }

    /**
     * Assert a value is a valid email address.
     */
    public function email(string $field, string $value, string $label = ''): self
    {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->add_error($field, "{$label} must be a valid email address.");
        }
        return $this;
    }

    // =========================================================================
    // CSRF PROTECTION
    // =========================================================================

    /**
     * Validate that the CSRF token in the POST request matches the session token.
     * Call generate_csrf_token() in your form to embed the token.
     */
    public function csrf(string $field = 'csrf_token'): self
    {
        $submitted = $_POST[$field] ?? '';
        $expected  = $_SESSION['csrf_token'] ?? '';

        if (empty($expected) || !hash_equals($expected, $submitted)) {
            $this->add_error($field, 'Invalid or expired security token. Please refresh the page and try again.');
        }
        return $this;
    }
    //Backend logic for request validation
    // =========================================================================
    // FILE UPLOAD RULES
    // =========================================================================

    /**
     * Validate an uploaded file ($_FILES entry).
     *
     * @param string   $field        The $_FILES key name
     * @param array    $file         The $_FILES[$field] array
     * @param string[] $allowed_types  MIME types allowed e.g. ['image/jpeg', 'application/pdf']
     * @param int      $max_bytes    Maximum file size in bytes
     */
    public function file_upload(
        string $field,
        array $file,
        array $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'],
        int $max_bytes = 2097152   // 2 MB default
    ): self {
        // No file uploaded
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            $this->add_error($field, 'Please select a file to upload.');
            return $this;
        }

        // PHP-level upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the server size limit.',
                UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the form size limit.',
                UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded. Please try again.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server error: missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Server error: failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A server extension stopped the file upload.',
            ];
            $msg = $upload_errors[$file['error']] ?? 'An unknown upload error occurred.';
            $this->add_error($field, $msg);
            return $this;
        }

        // File size check
        if ($file['size'] > $max_bytes) {
            $mb = number_format($max_bytes / 1048576, 1);
            $this->add_error($field, "File size must not exceed {$mb} MB.");
        }

        // MIME type check (use finfo for reliability — do not trust $_FILES['type'])
        if (!empty($allowed_types)) {
            $finfo     = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($file['tmp_name']);
            if (!in_array($mime_type, $allowed_types, true)) {
                $readable = implode(', ', $allowed_types);
                $this->add_error($field, "Invalid file type. Allowed types: {$readable}.");
            }
        }

        return $this;
    }

    // =========================================================================
    // BUSINESS RULE VALIDATIONS  (requires DB)
    // =========================================================================

    /**
     * Assert that no active (non-rejected) clearance request exists for a student.
     * Used in student/submit.php to prevent duplicate submissions.
     */
    public function no_active_request(string $field, string $student_id): self
    {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM clearance_requests
            WHERE student_id = ? AND status != 'rejected'
        ");
        $stmt->execute([$student_id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $this->add_error($field, 'You already have an active clearance request. You cannot submit a new one until it is fully processed or rejected.');
        }
        return $this;
    }

    /**
     * Assert a clearance item (by item_id) belongs to the given department
     * and is currently in 'pending' status.
     * Used in officer/action.php to prevent cross-department tampering.
     *
     * @param string $field        Error field key
     * @param string $item_id      The clearance_items.item_id value
     * @param string $department_id The officer's own department_id from session
     */
    public function item_belongs_to_department(string $field, string $item_id, string $department_id): self
    {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM clearance_items
            WHERE item_id = ? AND department_id = ? AND status = 'pending'
        ");
        $stmt->execute([$item_id, $department_id]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->add_error($field, 'This clearance item was not found, does not belong to your department, or has already been reviewed.');
        }
        return $this;
    }

    /**
     * Assert a rejected clearance item (by department_id) belongs to the
     * given student. Used in student/resubmit.php.
     *
     * @param string $field
     * @param string $department_id
     * @param string $student_id
     */
    public function rejected_item_belongs_to_student(string $field, string $department_id, string $student_id): self
    {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM clearance_items ci
            JOIN clearance_requests cr ON ci.request_id = cr.request_id
            WHERE ci.department_id = ? AND cr.student_id = ? AND ci.status = 'rejected'
        ");
        $stmt->execute([$department_id, $student_id]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->add_error($field, 'No rejected item found for this department under your account.');
        }
        return $this;
    }

    /**
     * Assert a user (by user_id) exists in the database with the given role.
     *
     * @param string $field
     * @param string $user_id
     * @param string $role   'student' | 'officer' | 'admin'
     */
    public function user_exists_with_role(string $field, string $user_id, string $role): self
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = ? AND role = ?");
        $stmt->execute([$user_id, $role]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->add_error($field, "No {$role} account found with the provided ID.");
        }
        return $this;
    }

    /**
     * Assert that a matric number matches the expected format.
     * Example format: CSC/2020/001  (DEPT/YEAR/SEQ)
     */
    public function matric_no_format(string $field, string $value): self
    {
        // Accepts formats like CSC/2020/001 or CSC/20/001
        if (!preg_match('/^[A-Z]{2,6}\/\d{2,4}\/\d{3,5}$/i', $value)) {
            $this->add_error($field, 'Matric number format is invalid. Expected format: DEPT/YEAR/SEQ (e.g. CSC/2020/001).');
        }
        return $this;
    }

    /**
     * Assert a UUID v4 string is well-formed.
     */
    public function uuid_format(string $field, string $value): self
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        if (!preg_match($pattern, $value)) {
            $this->add_error($field, 'The provided identifier is not a valid ID format.');
        }
        return $this;
    }
}

// =============================================================================
// CSRF TOKEN HELPERS  (standalone functions for use in templates)
// =============================================================================

/**
 * Generate and store a CSRF token in the session.
 * Call this once per form render and embed the result in a hidden input.
 *
 * Example (in your HTML form):
 *   <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
 */
function generate_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Regenerate the CSRF token (call after a successful form submission
 * to prevent token reuse / replay attacks).
 */
function regenerate_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

// =============================================================================
// CONVENIENCE WRAPPER  (quick one-shot validation, returns errors array)
// =============================================================================

/**
 * Validate a clearance submission (student submit.php).
 *
 * @param string $student_id  The logged-in student's user_id
 * @param string $level       The academic level submitted ('100L' … '500L')
 * @return array              Empty array on success, error messages on failure
 */
function validate_clearance_submission(string $student_id, string $level): array
{
    $v = new RequestValidator();

    $v->required('student_id', $student_id, 'Student ID')
      ->uuid_format('student_id', $student_id);

    $v->required('level', $level, 'Academic Level')
      ->in_list('level', $level, ['100L', '200L', '300L', '400L', '500L'], 'Academic Level');

    $v->no_active_request('general', $student_id);

    return $v->error_list();
}

/**
 * Validate an officer action (approve / reject) in officer/action.php.
 *
 * @param string $item_id       The clearance_items.item_id
 * @param string $action        'approve' or 'reject'
 * @param string $remark        Officer remark (required for rejection)
 * @param string $department_id The officer's department from session
 * @return array                Empty array on success, error messages on failure
 */
function validate_officer_action(
    string $item_id,
    string $action,
    string $remark,
    string $department_id
): array {
    $v = new RequestValidator();

    $v->required('log_id', $item_id, 'Item ID')
      ->uuid_format('log_id', $item_id);

    $v->required('action', $action, 'Action')
      ->in_list('action', $action, ['approve', 'reject'], 'Action');

    if ($action === 'reject') {
        $v->required('remark', $remark, 'Rejection remark')
          ->min_length('remark', $remark, 10, 'Rejection remark')
          ->max_length('remark', $remark, 1000, 'Rejection remark');
    }

    if ($action === 'approve' && !empty($remark)) {
        $v->max_length('remark', $remark, 500, 'Approval remark');
    }

    $v->required('department_id', $department_id, 'Department')
      ->uuid_format('department_id', $department_id);

    $v->item_belongs_to_department('log_id', $item_id, $department_id);

    return $v->error_list();
}

/**
 * Validate a student resubmission in student/resubmit.php.
 *
 * @param string $department_id  The department being resubmitted to
 * @param string $student_id     The logged-in student's user_id
 * @param string $explanation    The student's resubmission explanation
 * @return array                 Empty array on success, error messages on failure
 */
function validate_resubmission(
    string $department_id,
    string $student_id,
    string $explanation
): array {
    $v = new RequestValidator();

    $v->required('department_id', $department_id, 'Department ID')
      ->uuid_format('department_id', $department_id);

    $v->required('explanation', $explanation, 'Resolution explanation')
      ->min_length('explanation', $explanation, 20, 'Resolution explanation')
      ->max_length('explanation', $explanation, 2000, 'Resolution explanation');

    $v->rejected_item_belongs_to_student('department_id', $department_id, $student_id);

    return $v->error_list();
}
