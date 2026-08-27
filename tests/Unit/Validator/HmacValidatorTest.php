<?php

/*
 * HiPay payment integration for Sylius
 *
 * (c) Hipay
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Validator;

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Provider\AccountProvider;
use HiPay\SyliusHiPayPlugin\Validator\HmacValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\Request;

final class HmacValidatorTest extends TestCase
{
    private const PASSPHRASE = 'test-passphrase';

    private const BODY = 'custom_data[account_sylius_id]=1&transaction_reference=REF123&status=118';

    /**
     * sha256('') — the signature the SDK accepts in "redirection mode", when the payload
     * it derives from the query string is empty.
     */
    private const EMPTY_STRING_SHA256 = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    /** @var array<string, mixed> */
    private array $originalGet = [];

    protected function setUp(): void
    {
        $this->originalGet = $_GET;
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
    }

    /**
     * Regression test for the signature bypass.
     *
     * Before the fix, validate() called Signature::isValidHttpSignature() without the
     * signature and payload arguments. The SDK then read them from the superglobals and
     * switched to "redirection mode" because $_GET['hash'] was set, comparing the supplied
     * hash against the hash of the query string minus 'hash' — an empty string.
     *
     * This test fails against that implementation ("Failed asserting that true is false").
     */
    public function testItRejectsTheEmptyStringSignatureBypass(): void
    {
        $_GET = ['hash' => self::EMPTY_STRING_SHA256];

        $request = $this->createRequest(['HTTP_X_ALLOPASS_SIGNATURE' => self::EMPTY_STRING_SHA256]);

        self::assertFalse($this->createValidator()->validate($request));
    }

    public function testItAcceptsASignatureComputedOverTheRawBody(): void
    {
        $request = $this->createRequest([
            'HTTP_X_ALLOPASS_SIGNATURE' => hash('sha256', self::BODY . self::PASSPHRASE),
        ]);

        self::assertTrue($this->createValidator()->validate($request));
    }

    public function testItRejectsAWrongSignature(): void
    {
        $request = $this->createRequest([
            'HTTP_X_ALLOPASS_SIGNATURE' => hash('sha256', 'another-payload' . self::PASSPHRASE),
        ]);

        self::assertFalse($this->createValidator()->validate($request));
    }

    public function testItRejectsAMissingSignatureHeader(): void
    {
        self::assertFalse($this->createValidator()->validate($this->createRequest()));
    }

    public function testItRejectsAnEmptyBody(): void
    {
        $request = $this->createRequest(['HTTP_X_ALLOPASS_SIGNATURE' => 'irrelevant'], '');

        self::assertFalse($this->createValidator()->validate($request));
    }

    public function testItRejectsAnAccountWithAnEmptyPassphrase(): void
    {
        $request = $this->createRequest([
            'HTTP_X_ALLOPASS_SIGNATURE' => hash('sha256', self::BODY),
        ]);

        self::assertFalse($this->createValidator('')->validate($request));
    }

    public function testItStillFailsLoudlyWhenTheAccountIdIsMissing(): void
    {
        $request = $this->createRequest(
            ['HTTP_X_ALLOPASS_SIGNATURE' => 'irrelevant'],
            'transaction_reference=REF123&status=118',
        );

        $this->expectException(InvalidArgumentException::class);

        $this->createValidator()->validate($request);
    }

    private function createValidator(string $passphrase = self::PASSPHRASE): HmacValidator
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('getSecretPassphraseForCurrentEnv')->willReturn($passphrase);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn($account);

        return new HmacValidator(
            new AccountProvider($repository),
            $this->createMock(HiPayLoggerInterface::class),
        );
    }

    /**
     * @param array<string, string> $server
     */
    private function createRequest(array $server = [], string $body = self::BODY): Request
    {
        return Request::create('/payment/hipay/notify', 'POST', [], [], [], $server, $body);
    }
}
