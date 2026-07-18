<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\AuthService;
use App\Models\PartyModel;

class TestAuth extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:auth';
    protected $description = 'Runs the auth (BR-02) service against real data.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $auth = new AuthService();
        $partyModel = new PartyModel();
        $mobile = '+919333001001';

        CLI::write('=== BR-03: Mobile format validation ===', 'yellow');
        $this->assert(AuthService::isValidIndianMobile('+919876543210'), 'Valid 10-digit +91 number accepted');
        $this->assert(!AuthService::isValidIndianMobile('9876543210'), 'Missing +91 prefix rejected');
        $this->assert(!AuthService::isValidIndianMobile('+91123456789'), 'Number not starting 6-9 rejected');
        $this->assert(!AuthService::isValidIndianMobile('+9198765432100'), 'Too many digits rejected');

        CLI::write("\n=== BR-02: Registration OTP flow ===", 'yellow');
        $otp = $auth->requestOtp($mobile, 'registration');
        $this->assert(strlen($otp) === 6, 'Generated OTP is 6 digits');

        $wrongOtpResult = $auth->verifyOtp($mobile, 'registration', '000000');
        $this->assert($wrongOtpResult === false || $otp === '000000', 'Wrong OTP correctly rejected');

        $correctResult = $auth->verifyOtp($mobile, 'registration', $otp);
        $this->assert($correctResult === true, 'Correct OTP verified successfully');

        $reuseResult = $auth->verifyOtp($mobile, 'registration', $otp);
        $this->assert($reuseResult === false, 'Already-verified OTP cannot be reused');

        CLI::write("\n=== BR-02: Party creation after OTP verification ===", 'yellow');
        $party = $auth->completeRegistration($mobile);
        $this->assert($party['mobile_number'] === $mobile, 'Party created with correct mobile number');
        $this->assert($party['mobile_verified_at'] !== null, 'mobile_verified_at is set');
        $this->assert((float) $party['star_rating'] === 3.0, 'BR-35: new party still defaults to 3.0 rating');

        $again = $auth->completeRegistration($mobile);
        $this->assert($again['id'] === $party['id'], 'Re-registering the same mobile returns the existing party, not a duplicate');

        CLI::write("\n=== BR-02: mPIN setup and correct login ===", 'yellow');
        $auth->setMpin($party['id'], '1234');
        $loginResult = $auth->authenticateWithMpin($mobile, '1234');
        $this->assert($loginResult['status'] === 'ok', 'Correct mPIN authenticates successfully');

        CLI::write("\n=== BR-02: 3-consecutive-failure lockout triggers OTP requirement ===", 'yellow');
        $r1 = $auth->authenticateWithMpin($mobile, '0000');
        $this->assert($r1['status'] === 'invalid_mpin' && $r1['attemptsRemaining'] === 2, 'Attempt 1: invalid, 2 remaining');

        $r2 = $auth->authenticateWithMpin($mobile, '0000');
        $this->assert($r2['status'] === 'invalid_mpin' && $r2['attemptsRemaining'] === 1, 'Attempt 2: invalid, 1 remaining');

        $r3 = $auth->authenticateWithMpin($mobile, '0000');
        $this->assert($r3['status'] === 'otp_required', 'BR-02: 3rd consecutive failure triggers OTP requirement, not a 4th silent attempt');

        CLI::write("\n=== BR-02: mPIN reset via OTP after lockout ===", 'yellow');
        $resetOtp = $auth->requestOtp($mobile, 'mpin_reset');
        $verified = $auth->verifyOtp($mobile, 'mpin_reset', $resetOtp);
        $this->assert($verified === true, 'Reset OTP verified');

        $auth->resetMpinAfterOtp($party['id'], '5678');
        $newLogin = $auth->authenticateWithMpin($mobile, '5678');
        $this->assert($newLogin['status'] === 'ok', 'New mPIN works after OTP-gated reset');

        $current = $partyModel->find($party['id']);
        $this->assert((int) $current['failed_mpin_attempts'] === 0, 'Failed attempt counter reset after successful login');

        $oldPinResult = $auth->authenticateWithMpin($mobile, '1234');
        $this->assert($oldPinResult['status'] === 'invalid_mpin', 'Old mPIN no longer works after reset');

        CLI::write("\n" . ($this->fail === 0 ? "🎉 ALL {$this->pass} ASSERTIONS PASSED" : "❌ {$this->fail} FAILURES, {$this->pass} passed"), $this->fail === 0 ? 'green' : 'red');
    }

    private function assert(bool $cond, string $msg): void
    {
        if ($cond) {
            $this->pass++;
            CLI::write("  \xE2\x9C\x93 {$msg}", 'green');
        } else {
            $this->fail++;
            CLI::write("  \xE2\x9C\x97 ASSERTION FAILED: {$msg}", 'red');
        }
    }
}
