<?php
/**
 * expAiLane and expAiLanePolicy: the lanes a provider and a model offer, their settings, the provider of a lane, and
 * the choice of a lane for a request (order, prices, the fast lane, urgent requests, deadlines). Settings in memory.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

require_once __DIR__ . '/expait386ehelpers.php';

class expAiLanePolicyTest extends expAiT386ETestCase
{
    private function lanes( array $more = array() )
    {
        return $this->config( $more + array(
            'ProviderSettings' => array( 'Providers' => array( 't386el', 't386ec', 't386ex' ) ),
            'Provider_t386el' => array( 'Type' => 'openai_chat', 'BaseURL' => 'https://lanes.example/v1', 'ApiKey' => 'k', 'Retention' => 'zdr', 'Lanes' => array( 'flex', 'batch', 'fast', ' ' ) ),
            'Provider_t386ec' => array( 'Type' => 'custom', 'Class' => 'expAiOpenAiChatProvider' ),
            'Provider_t386ex' => array( 'Type' => 'mystery' ),
            'Model_t386el.a' => array( 'Provider' => 't386el', 'Id' => 'model-a', 'Lanes' => array( 'flex' ) ),
            'Model_t386el.b' => array( 'Provider' => 't386el', 'Id' => 'model-b' ),
            'Model_t386ec.c' => array( 'Provider' => 't386ec', 'Id' => 'model-c' ),
            'Model_t386ex.x' => array( 'Provider' => 't386ex', 'Id' => 'model-x' ),
            'Lane_t386el.flex' => array( 'Enabled' => 'true', 'PriceMultiplier' => '0.5', 'Retention' => '6w', 'Timeout' => 30, 'RequestParams' => array( 'service_tier' => 'flex', 'model' => 'never', 'n' => '2' ),
                                         'Headers' => array( 'X-Lane: flex', 'Authorization: no' ) ),
            'Lane_t386el.batch' => array( 'Enabled' => 'true', 'PriceMultiplier' => '0.5', 'BatchApi' => 'openaichat', 'Models' => array( 'model-b' ), 'Retention' => 'forever' ),
            'Lane_t386el.fast' => array( 'Enabled' => 'true', 'PriceMultiplier' => '6' ),
            'Lane_t386el.priority' => array( 'Enabled' => 'true', 'PriceMultiplier' => '2' ),
            'Lane_t386el.Bad' => array( 'Enabled' => 'true' ),
            'Lane_t386ec.flex' => array( 'Enabled' => 'true', 'PriceMultiplier' => '0.5' ),
        ) );
    }

    public function testTheLanesOfAProviderAndOfAModel(): void
    {
        $config = $this->lanes();
        $this->assertSame( array(), expAiLane::forProvider( 'nothing', $config ) );
        $this->assertSame( array( 'standard', 'flex', 'batch', 'fast' ), array_keys( expAiLane::forProvider( 't386el', $config ) ), 'Lanes[] limits them; priority is not listed' );
        $this->assertSame( array( 'standard', 'flex' ), array_keys( expAiLane::forModel( 't386el.a', $config ) ), 'the model limits them too' );
        $this->assertSame( array( 'standard', 'flex', 'batch', 'fast' ), array_keys( expAiLane::forModel( 't386el.b', $config ) ), 'without Lanes[] of its own a model has every lane of its provider' );
        $this->assertSame( array(), expAiLane::forModel( 'nothing', $config ) );
        $this->assertNull( expAiLane::forModelLane( 't386el.a', 'batch', $config ) );
        $this->assertSame( 'flex', expAiLane::forModelLane( 't386el.a', 'flex', $config )->name() );

        $flex = expAiLane::fetch( 't386el', 'flex', $config );
        $this->assertSame( array( '6w', 50, 30 ), array( $flex->retention(), $flex->factorPercent(), $flex->get( 'timeout' ) ) );
        $this->assertFalse( $flex->zeroRetention() );
        $this->assertSame( array( 'service_tier' => 'flex', 'n' => 2 ), $flex->get( 'params' ) );
        $this->assertSame( array( 'X-Lane: flex' ), $flex->get( 'headers' ) );
        $this->assertSame( 100, $flex->billedFactor( 'default' ), 'served at the default tier: billed as standard' );
        $this->assertSame( 50, $flex->billedFactor( 'flex' ) );
        $batch = expAiLane::fetch( 't386el', 'batch', $config );
        $this->assertSame( array( 'openai', 'unknown', true ), array( $batch->get( 'batch_api' ), $batch->retention(), $batch->isBatch() ) );
        $standard = expAiLane::fetch( 't386el', 'standard', $config );
        $this->assertSame( array( 'zdr', 100, true ), array( $standard->retention(), $standard->factorPercent(), $standard->zeroRetention() ) );
        $this->assertSame( 't386el:standard', $standard->key() );
        $this->assertSame( 't386el', $standard->toArray()['provider'] );
    }

    public function testTheProviderOfALane(): void
    {
        $config = $this->lanes();
        $flex = expAiLane::fetch( 't386el', 'flex', $config );
        $settings = $flex->providerSettings( 't386el.a', $config );
        $this->assertSame( array( 30, 'flex' ), array( $settings['Timeout'], $settings['Lane'] ) );
        $this->assertSame( array( 'service_tier' => 'flex', 'n' => 2 ), json_decode( $settings['ExtraBody'], true ) );
        $this->assertContains( 'X-Lane: flex', $settings['Headers'] );
        $this->assertInstanceOf( 'expAiOpenAiChatProvider', $flex->provider( 't386el.a', $config ) );
        $this->assertSame( array(), $flex->providerSettings( 'nothing', $config ) );
        try
        {
            $flex->provider( 'nothing', $config );
            $this->fail( 'no model' );
        }
        catch ( expAiException $e )
        {
            $this->assertSame( 'no model nothing in the provider registry', $e->detail() );
        }
        $this->assertInstanceOf( 'expAiOpenAiChatProvider', expAiLane::fetch( 't386ec', 'standard', $config )->provider( 't386ec.c', $config ), 'a custom provider on its standard lane' );
        foreach ( array( array( 't386ec', 'flex', 't386ec.c', 'a custom provider has only the lane standard' ), array( 't386ex', 'standard', 't386ex.x', 'unknown provider Type mystery' ) ) as $case )
        {
            try
            {
                expAiLane::fetch( $case[0], $case[1], $config )->provider( $case[2], $config );
                $this->fail( 'built ' . $case[0] );
            }
            catch ( expAiException $e )
            {
                $this->assertSame( $case[3], $e->detail() );
            }
        }
        $policy = $flex->dataPolicy( $config );
        $this->assertInstanceOf( 'expAiDataPolicy', $policy );
    }

    public function testTheOrderOfTheLanes(): void
    {
        $policy = new expAiLanePolicy( $this->lanes( array( 'LanePolicy' => array( 'Order' => array( 'seo' => 'batch flex', 'default' => 'flex, standard' ), 'ClassOrder' => array( 'bulk' => 'batch;Bad' ) ) ) ) );
        $this->assertSame( array( 'batch', 'flex', 'standard' ), $policy->order( 'seo' ) );
        $this->assertSame( array( 'batch', 'standard' ), $policy->order( '', 'bulk' ) );
        $this->assertSame( array( 'flex', 'standard' ), $policy->order( 'other' ) );
        $this->assertSame( array( 'standard' ), ( new expAiLanePolicy( $this->lanes( array( 'LanePolicy' => array( 'Order' => array(), 'ClassOrder' => array() ) ) ) ) )->order() );
    }

    public function testTheChoiceOfALaneForARequest(): void
    {
        $this->requireDatabase();
        $this->asUser( 'admin' );
        $config = $this->lanes( array( 'LanePolicy' => array( 'Order' => array( 'default' => 'batch fast flex standard' ), 'UrgentPriority' => 80, 'StrictDeadline' => 'enabled' ) ) );
        $policy = new expAiLanePolicy( $config );
        $choice = $policy->choose( 't386el.b' );
        $this->assertSame( 'batch', $choice['lane']->name() );
        $urgent = $policy->choose( 't386el.b', array( 'priority' => 90 ) );
        $this->assertSame( 'standard', $urgent['lane']->name() );
        $this->assertSame( array( 'batch' => 'too slow for an urgent request', 'fast' => 'only on request', 'flex' => 'too slow for an urgent request' ), $urgent['tried'] );
        $this->assertSame( 'fast', $policy->choose( 't386el.b', array( 'priority' => 90, 'allow_fast' => true ) )['lane']->name() );
        $soon = $policy->choose( 't386el.b', array( 'deadline' => time() + 600 ) );
        $this->assertStringStartsWith( 'expected to take 86400 s, the deadline is in ', $soon['tried']['batch'] );
        $this->assertSame( 'standard', $policy->choose( 't386el.b', array( 'lane' => 'standard' ) )['lane']->name() );

        $this->asUser( 'anonymous' );
        $this->assertSame( 'needs the policy expai/lane_fast', $policy->refusal( expAiLane::fetch( 't386el', 'fast', $config ), array( 'priority' => 50, 'deadline' => 0, 'user' => null,
                                                                                                                                         'requirements' => array() ), true ) );
        $this->asUser( 'admin' );
        $off = $this->lanes( array( 'Lane_t386el.batch' => array( 'Enabled' => 'false', 'PriceMultiplier' => '0.5', 'Models' => array( 'model-b' ) ),
                                    'Lane_t386el.fast' => array( 'Enabled' => 'true' ) ) );
        $policy = new expAiLanePolicy( $off );
        $request = array( 'priority' => 50, 'deadline' => 0, 'user' => null, 'requirements' => array(), 'allow_fast' => true );
        $this->assertSame( 'disabled', $policy->refusal( expAiLane::fetch( 't386el', 'batch', $off ), $request ) );
        $this->assertSame( 'no confirmed price', $policy->refusal( expAiLane::fetch( 't386el', 'fast', $off ), $request ) );
        try
        {
            $policy->choose( 't386el.b', array( 'lane' => 'batch' ) );
            $this->fail( 'chose a disabled lane' );
        }
        catch ( expAiException $e )
        {
            $this->assertSame( 'batch: disabled', $e->detail() );
            $this->assertSame( array( '%model' => 't386el.b' ), $e->arguments() );
        }
        try
        {
            $policy->choose( 'nothing' );
            $this->fail( 'chose a lane of nothing' );
        }
        catch ( expAiException $e )
        {
            $this->assertSame( 'no model nothing in the provider registry', $e->detail() );
        }
    }
}
