<?php
/**
 * Server-side validation of the quotation request form.
 */
declare(strict_types=1);

namespace Excal\Quote\Model;

use Magento\Framework\Phrase;
use Magento\Framework\Validator\EmailAddress;

/**
 * Normalises and validates posted contact data.
 */
class FormValidator
{
    /**
     * field => [label, required, max length, multiline]
     */
    private const FIELDS = [
        'organisation' => ['Organisation', true, 255, false],
        'department' => ['Department', false, 255, false],
        'contact_name' => ['Contact name', true, 255, false],
        'email' => ['Email', true, 255, false],
        'phone' => ['Phone', true, 64, false],
        'billing_address' => ['Billing address', true, 2000, true],
        'delivery_address' => ['Delivery address', true, 2000, true],
        'customer_reference' => ['Your reference / PO number', false, 128, false],
        'notes' => ['Notes', false, 4000, true],
    ];

    /**
     * @param EmailAddress $emailValidator
     */
    public function __construct(
        private readonly EmailAddress $emailValidator
    ) {
    }

    /**
     * Validate posted data.
     *
     * @param array $post
     * @return array{0: array<string,string>, 1: Phrase[]} [clean data, errors]
     */
    public function validate(array $post): array
    {
        $data = [];
        $errors = [];
        foreach (self::FIELDS as $field => [$label, $required, $maxLength, $multiline]) {
            $value = is_scalar($post[$field] ?? null) ? (string)$post[$field] : '';
            $value = $multiline
                ? trim(preg_replace("/\r\n?/", "\n", $value) ?? '')
                : trim(preg_replace('/\s+/u', ' ', $value) ?? '');
            // Strip control characters except newlines in multiline fields.
            $value = preg_replace($multiline ? '/[^\P{C}\n]/u' : '/\p{C}/u', '', $value) ?? '';
            $data[$field] = $value;

            if ($required && $value === '') {
                $errors[] = __('%1 is required.', __($label));
            } elseif (mb_strlen($value) > $maxLength) {
                $errors[] = __('%1 must be at most %2 characters.', __($label), $maxLength);
            }
        }

        if ($data['email'] !== '' && !$this->emailValidator->isValid($data['email'])) {
            $errors[] = __('Please enter a valid email address.');
        }
        if ($data['phone'] !== '' && !preg_match('/^[0-9+()\-.\s]{6,64}$/', $data['phone'])) {
            $errors[] = __('Please enter a valid phone number.');
        }
        return [$data, $errors];
    }
}
