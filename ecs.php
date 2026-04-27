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

/**
 * ECS configuration — PHP 8.2, Sylius Labs preset + Slevomat sniffs.
 */

use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use SlevomatCodingStandard\Sniffs\ControlStructures\RequireNullCoalesceOperatorSniff;
use SlevomatCodingStandard\Sniffs\Exceptions\DeadCatchSniff;
use SlevomatCodingStandard\Sniffs\Functions\UnusedParameterSniff;
use SlevomatCodingStandard\Sniffs\Namespaces\ReferenceUsedNamesOnlySniff;
use SlevomatCodingStandard\Sniffs\TypeHints\NullTypeHintOnLastPositionSniff;
use SlevomatCodingStandard\Sniffs\Variables\UselessVariableSniff;
use Symplify\EasyCodingStandard\Config\ECSConfig;

$fileHeaderComment = <<<'EOF'
HiPay payment integration for Sylius

(c) Hipay

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF;

return static function (ECSConfig $ecsConfig) use ($fileHeaderComment): void {
    $ecsConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/config',
        __DIR__ . '/tests',
        __DIR__ . '/ecs.php',
    ]);

    $ecsConfig->import('vendor/sylius-labs/coding-standard/ecs.php');

    $ecsConfig->ruleWithConfiguration(HeaderCommentFixer::class, [
        'header' => $fileHeaderComment,
        'location' => 'after_open',
    ]);

    // Slevomat sniffs: stricter code quality (recommendations)
    // ParameterTypeHintSniff / ReturnTypeHintSniff can be added when PHPDoc traversable specs (@return array<string,mixed>, etc.) are complete
    $ecsConfig->rule(DeadCatchSniff::class);
    $ecsConfig->rule(RequireNullCoalesceOperatorSniff::class);
    $ecsConfig->rule(NullTypeHintOnLastPositionSniff::class);
    $ecsConfig->ruleWithConfiguration(ReferenceUsedNamesOnlySniff::class, [
        'searchAnnotations' => false,
        'allowFallbackGlobalFunctions' => true,
        'allowFallbackGlobalConstants' => true,
    ]);
    $ecsConfig->rule(UselessVariableSniff::class);
    $ecsConfig->rule(UnusedParameterSniff::class);

    // Skip UnusedParameter for interface implementations and interface-required params
    $ecsConfig->skip([
        UnusedParameterSniff::class => [
            '*/OrderPay/Provider/*.php',
            '*/CommandProvider/*.php',
            '*/Client/HiPayClient.php', // capturePayment($currency) required by HiPayClientInterface
            '*/Migrations/*.php', // Schema $schema required by Doctrine Migrations interface
            '*/Provider/PaymentProductProvider.php', // getCodesByAccount($account) interface stub
            '*/Twig/Component/Admin/PaymentMethodFormComponent.php', // dehydrateGatewayFactoryName($value) LiveComponent callback
            '*/Fixture/Factory/AccountExampleFactory.php', // Options $options required by OptionsResolver lazy defaults
            '*/Webhook/Scheduler/ProcessPendingBatchHandler.php', // $message required by Messenger __invoke convention
        ],
    ]);
};
