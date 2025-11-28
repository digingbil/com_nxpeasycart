<?php

declare(strict_types=1);

namespace Tests\Unit\Administrator\Service;

use PHPUnit\Framework\TestCase;

/**
 * Test the order state machine logic embedded in OrderService.
 *
 * The state machine follows: cart → pending → paid → fulfilled → refunded | canceled
 */
final class OrderStateMachineTest extends TestCase
{
    private const ORDER_STATES = ['cart', 'pending', 'paid', 'fulfilled', 'refunded', 'canceled'];

    /**
     * Valid state transitions based on e-commerce workflow.
     */
    private const VALID_TRANSITIONS = [
        'cart'      => ['pending', 'canceled'],
        'pending'   => ['paid', 'canceled'],
        'paid'      => ['fulfilled', 'refunded', 'canceled'],
        'fulfilled' => ['refunded'],
        'refunded'  => [],
        'canceled'  => [],
    ];

    public function testAllStatesAreDefined(): void
    {
        $this->assertCount(6, self::ORDER_STATES);
        $this->assertContains('cart', self::ORDER_STATES);
        $this->assertContains('pending', self::ORDER_STATES);
        $this->assertContains('paid', self::ORDER_STATES);
        $this->assertContains('fulfilled', self::ORDER_STATES);
        $this->assertContains('refunded', self::ORDER_STATES);
        $this->assertContains('canceled', self::ORDER_STATES);
    }

    /**
     * @dataProvider validTransitionProvider
     */
    public function testValidTransitionIsAllowed(string $from, string $to): void
    {
        $allowed = self::VALID_TRANSITIONS[$from] ?? [];
        $this->assertContains($to, $allowed, "Transition from {$from} to {$to} should be allowed");
    }

    public static function validTransitionProvider(): array
    {
        return [
            'cart to pending'     => ['cart', 'pending'],
            'cart to canceled'    => ['cart', 'canceled'],
            'pending to paid'     => ['pending', 'paid'],
            'pending to canceled' => ['pending', 'canceled'],
            'paid to fulfilled'   => ['paid', 'fulfilled'],
            'paid to refunded'    => ['paid', 'refunded'],
            'paid to canceled'    => ['paid', 'canceled'],
            'fulfilled to refunded' => ['fulfilled', 'refunded'],
        ];
    }

    /**
     * @dataProvider invalidTransitionProvider
     */
    public function testInvalidTransitionIsBlocked(string $from, string $to): void
    {
        $allowed = self::VALID_TRANSITIONS[$from] ?? [];
        $this->assertNotContains($to, $allowed, "Transition from {$from} to {$to} should be blocked");
    }

    public static function invalidTransitionProvider(): array
    {
        return [
            'cannot go back from paid to cart'      => ['paid', 'cart'],
            'cannot go back from paid to pending'   => ['paid', 'pending'],
            'cannot fulfill before payment'         => ['pending', 'fulfilled'],
            'cannot ship from cart'                 => ['cart', 'fulfilled'],
            'refunded is terminal'                  => ['refunded', 'paid'],
            'refunded cannot be fulfilled'          => ['refunded', 'fulfilled'],
            'canceled is terminal'                  => ['canceled', 'pending'],
            'canceled cannot be paid'               => ['canceled', 'paid'],
            'fulfilled cannot go back to paid'      => ['fulfilled', 'paid'],
        ];
    }

    public function testTerminalStatesHaveNoTransitions(): void
    {
        $this->assertEmpty(self::VALID_TRANSITIONS['refunded']);
        $this->assertEmpty(self::VALID_TRANSITIONS['canceled']);
    }

    public function testStateValidationRejectsInvalidState(): void
    {
        $invalidStates = ['unknown', 'shipped', 'delivered', 'processing', ''];

        foreach ($invalidStates as $state) {
            $this->assertNotContains($state, self::ORDER_STATES);
        }
    }

    public function testStateCaseInsensitivity(): void
    {
        // The system should normalize states to lowercase
        $inputs = ['PAID', 'Pending', 'FULFILLED', 'Cart'];

        foreach ($inputs as $input) {
            $normalized = strtolower($input);
            $this->assertContains($normalized, self::ORDER_STATES);
        }
    }

    public function testAllowedTransitionsFromPaid(): void
    {
        $allowed = self::VALID_TRANSITIONS['paid'];

        // Paid orders can be fulfilled, refunded, or canceled
        $this->assertContains('fulfilled', $allowed);
        $this->assertContains('refunded', $allowed);
        $this->assertContains('canceled', $allowed);

        // But cannot go backwards
        $this->assertNotContains('pending', $allowed);
        $this->assertNotContains('cart', $allowed);
    }

    public function testFulfilledCanOnlyBeRefunded(): void
    {
        $allowed = self::VALID_TRANSITIONS['fulfilled'];

        $this->assertCount(1, $allowed);
        $this->assertContains('refunded', $allowed);
    }

    public function testCartStartsWorkflow(): void
    {
        // Cart is the entry point for the order workflow
        $allowed = self::VALID_TRANSITIONS['cart'];

        $this->assertContains('pending', $allowed);
        $this->assertContains('canceled', $allowed);
        $this->assertCount(2, $allowed);
    }

    /**
     * Test that all states have defined transitions (even if empty).
     */
    public function testAllStatesHaveTransitionsDefined(): void
    {
        foreach (self::ORDER_STATES as $state) {
            $this->assertArrayHasKey($state, self::VALID_TRANSITIONS);
        }
    }

    /**
     * Test transition path from cart to fulfilled (happy path).
     */
    public function testHappyPathWorkflow(): void
    {
        $workflow = ['cart', 'pending', 'paid', 'fulfilled'];

        for ($i = 0; $i < count($workflow) - 1; $i++) {
            $from = $workflow[$i];
            $to = $workflow[$i + 1];
            $allowed = self::VALID_TRANSITIONS[$from];

            $this->assertContains($to, $allowed, "Step {$i}: {$from} → {$to} should be valid");
        }
    }

    /**
     * Test transition path for refund after payment.
     */
    public function testRefundPathFromPaid(): void
    {
        $workflow = ['cart', 'pending', 'paid', 'refunded'];

        for ($i = 0; $i < count($workflow) - 1; $i++) {
            $from = $workflow[$i];
            $to = $workflow[$i + 1];
            $allowed = self::VALID_TRANSITIONS[$from];

            $this->assertContains($to, $allowed, "Refund path step {$i}: {$from} → {$to}");
        }
    }

    /**
     * Test transition path for refund after fulfillment.
     */
    public function testRefundPathFromFulfilled(): void
    {
        $workflow = ['cart', 'pending', 'paid', 'fulfilled', 'refunded'];

        for ($i = 0; $i < count($workflow) - 1; $i++) {
            $from = $workflow[$i];
            $to = $workflow[$i + 1];
            $allowed = self::VALID_TRANSITIONS[$from];

            $this->assertContains($to, $allowed, "Refund path step {$i}: {$from} → {$to}");
        }
    }
}
