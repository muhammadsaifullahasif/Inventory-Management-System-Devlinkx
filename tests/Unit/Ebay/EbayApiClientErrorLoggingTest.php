<?php

namespace Tests\Unit\Ebay;

use App\Services\Ebay\EbayApiClient;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class EbayApiClientErrorLoggingTest extends TestCase
{
    public function test_failure_is_logged_with_code_build_and_correlation_id_and_message_is_unchanged(): void
    {
        Log::shouldReceive('channel')->with('ebay')->once()->andReturnSelf();
        Log::shouldReceive('error')->once()->withArgs(function (string $message, array $context) {
            return $message === 'eBay API call returned Ack=Failure'
                && $context['errors'][0]['code'] === '10007'
                && $context['errors'][0]['short'] === 'System error.'
                && $context['build'] === 'E1234_CORE_API_19000'
                && $context['correlation_id'] === 'abc-123';
        });

        $response = [
            'Ack' => 'Failure',
            'Build' => 'E1234_CORE_API_19000',
            'CorrelationID' => 'abc-123',
            'Errors' => [
                'ErrorCode' => '10007',
                'SeverityCode' => 'Error',
                'ShortMessage' => 'System error.',
                'LongMessage' => 'Unable to process your request. Please try again later.',
            ],
        ];

        $this->expectExceptionMessage('eBay API Error: System error. - Unable to process your request. Please try again later.');

        (new EbayApiClient())->checkForErrors($response);
    }

    public function test_success_is_not_logged(): void
    {
        Log::shouldReceive('channel')->never();

        (new EbayApiClient())->checkForErrors(['Ack' => 'Success']);

        $this->addToAssertionCount(1);
    }
}
