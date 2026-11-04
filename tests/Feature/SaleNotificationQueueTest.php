<?php

namespace Tests\Feature;

use App\Actions\Sales\CreateSaleAction;
use App\Jobs\SendSaleNotification;
use App\Mail\SaleNotificationMail;
use App\Models\Category;
use App\Models\Color;
use App\Models\NotificationEmail;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SaleNotificationQueueTest extends TestCase
{
    use DatabaseMigrations;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->manager = User::factory()->create(['active' => true]);
        $this->manager->assignRole('gerente');

        NotificationEmail::query()->create(['email' => 'owner@example.test', 'label' => 'Owner']);
        NotificationEmail::query()->create(['email' => 'manager@example.test', 'label' => 'Manager']);
        Mail::fake();
    }

    public function test_api_sale_queues_one_job_per_recipient_even_when_default_queue_is_sync(): void
    {
        config(['queue.default' => 'sync']);
        $variant = $this->variant();

        $response = $this->api()->postJson('/api/sales', $this->salePayload($variant));

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['sale' => ['id', 'folio', 'status', 'subtotal', 'total', 'items', 'payments']]]);

        $this->assertDatabaseCount('jobs', 2);
        $this->assertDatabaseHas('jobs', ['queue' => 'sale-notifications']);
        $jobs = DB::table('jobs')->where('queue', 'sale-notifications')->get();
        $this->assertCount(2, $jobs);
        foreach ($jobs as $queuedJob) {
            $this->assertStringContainsString('SendSaleNotification', $queuedJob->payload);
            $this->assertGreaterThanOrEqual(
                $queuedJob->created_at + 55,
                $queuedJob->available_at,
            );
        }
        Mail::assertNothingSent();
    }

    public function test_notifications_wait_for_commit_and_are_discarded_on_rollback(): void
    {
        $variant = $this->variant();
        $action = app(CreateSaleAction::class);

        DB::beginTransaction();
        $sale = $action->execute($this->actionPayload($variant), $this->manager);
        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
        $this->assertDatabaseCount('jobs', 0);
        DB::commit();
        $this->assertDatabaseCount('jobs', 2);

        DB::table('jobs')->delete();
        $rollbackVariant = $this->variant();
        DB::beginTransaction();
        $rolledBackSale = $action->execute($this->actionPayload($rollbackVariant), $this->manager);
        DB::rollBack();

        $this->assertDatabaseMissing('sales', ['id' => $rolledBackSale->id]);
        $this->assertDatabaseCount('jobs', 0);
        Mail::assertNothingSent();
    }

    public function test_queue_registration_failure_does_not_turn_a_committed_api_sale_into_an_error(): void
    {
        config(['queue.connections.sale_notifications.driver' => 'unavailable']);

        $variant = $this->variant();
        $response = $this->api()->postJson('/api/sales', $this->salePayload($variant));

        $response->assertCreated();
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => 4]);
        Mail::assertNothingSent();
    }

    public function test_smtp_failure_is_confined_to_the_job_and_sale_remains_committed(): void
    {
        $variant = $this->variant();
        $sale = app(CreateSaleAction::class)->execute($this->actionPayload($variant), $this->manager);
        $recipient = NotificationEmail::query()->firstOrFail();
        $job = new SendSaleNotification((int) $sale->id, (int) $this->manager->id, (int) $recipient->id);

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300], $job->backoff());

        Mail::shouldReceive('to')
            ->once()
            ->with($recipient->email)
            ->andThrow(new \RuntimeException('Simulated SMTP 535'));

        try {
            $job->handle();
            $this->fail('SMTP failure should be retried by Laravel’s queue worker.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated SMTP 535', $exception->getMessage());
        }

        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'status' => 'completed']);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => 4]);
    }

    public function test_job_sends_the_sale_mail_only_when_the_worker_handles_it(): void
    {
        $variant = $this->variant();
        $sale = app(CreateSaleAction::class)->execute($this->actionPayload($variant), $this->manager);
        $recipient = NotificationEmail::query()->firstOrFail();

        Mail::assertNothingSent();
        (new SendSaleNotification((int) $sale->id, (int) $this->manager->id, (int) $recipient->id))->handle();

        Mail::assertSent(SaleNotificationMail::class, fn (SaleNotificationMail $mail): bool => $mail->hasTo($recipient->email)
        );
    }

    public function test_bounded_worker_processes_only_sale_notifications_once_and_stops_empty(): void
    {
        $variant = $this->variant();
        $sale = app(CreateSaleAction::class)->execute($this->actionPayload($variant), $this->manager);
        $recipients = NotificationEmail::query()->orderBy('id')->get();

        DB::table('jobs')->delete();
        $queue = app('queue')->connection('sale_notifications');
        $queue->push(new SendSaleNotification((int) $sale->id, (int) $this->manager->id, (int) $recipients[0]->id), '', 'sale-notifications');
        $queue->push(new SendSaleNotification((int) $sale->id, (int) $this->manager->id, (int) $recipients[1]->id), '', 'default');
        $this->assertDatabaseCount('jobs', 2);

        $this->assertSame(60, (new SendSaleNotification((int) $sale->id, (int) $this->manager->id, (int) $recipients[0]->id))->timeout);
        $this->assertSame(180, config('queue.connections.sale_notifications.retry_after'));
        $this->assertSame(10, config('mail.mailers.smtp.timeout'));

        $workerOptions = [
            'connection' => 'sale_notifications',
            '--queue' => 'sale-notifications',
            '--sleep' => 0,
            '--tries' => 3,
            '--timeout' => 60,
            '--max-time' => 60,
            '--max-jobs' => 20,
            '--stop-when-empty' => true,
        ];

        Artisan::call('queue:work', $workerOptions);

        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseHas('jobs', ['queue' => 'default']);
        Mail::assertSent(SaleNotificationMail::class, fn (SaleNotificationMail $mail): bool => $mail->hasTo($recipients[0]->email));
        Mail::assertNotSent(SaleNotificationMail::class, fn (SaleNotificationMail $mail): bool => $mail->hasTo($recipients[1]->email));

        Artisan::call('queue:work', $workerOptions);

        $this->assertDatabaseCount('jobs', 1);
        Mail::assertSent(SaleNotificationMail::class, 1);
    }

    public function test_bounded_worker_retries_smtp_failure_then_records_failed_job_without_reverting_sale(): void
    {
        $variant = $this->variant();
        $sale = app(CreateSaleAction::class)->execute($this->actionPayload($variant), $this->manager);
        $recipient = NotificationEmail::query()->firstOrFail();

        DB::table('jobs')->delete();
        app('queue')->connection('sale_notifications')->push(
            new SendSaleNotification((int) $sale->id, (int) $this->manager->id, (int) $recipient->id),
            '',
            'sale-notifications',
        );
        $this->assertDatabaseCount('jobs', 1);

        Mail::shouldReceive('to')
            ->times(3)
            ->with($recipient->email)
            ->andThrow(new \RuntimeException('Simulated SMTP 535'));

        $workerOptions = [
            'connection' => 'sale_notifications',
            '--queue' => 'sale-notifications',
            '--sleep' => 0,
            '--tries' => 3,
            '--timeout' => 60,
            '--max-time' => 60,
            '--max-jobs' => 20,
            '--stop-when-empty' => true,
        ];

        Artisan::call('queue:work', $workerOptions);
        $this->assertDatabaseCount('jobs', 1);
        $this->travel(61)->seconds();

        Artisan::call('queue:work', $workerOptions);
        $this->assertDatabaseCount('jobs', 1);
        $this->travel(301)->seconds();

        Artisan::call('queue:work', $workerOptions);

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'status' => 'completed']);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => 4]);
    }

    public function test_repeated_dispatch_for_the_same_sale_and_recipient_is_deduplicated_while_pending(): void
    {
        $variant = $this->variant();
        $sale = app(CreateSaleAction::class)->execute($this->actionPayload($variant), $this->manager);

        app(\App\Services\NotificationService::class)->notifySale($sale, $this->manager);

        $this->assertDatabaseCount('jobs', 2);
        Mail::assertNothingSent();
    }

    public function test_local_legacy_vs_queued_latency_benchmark(): void
    {
        if (env('RUN_SALE_LATENCY_BENCHMARK') !== '1') {
            $this->markTestSkipped('Run explicitly with RUN_SALE_LATENCY_BENCHMARK=1.');
        }

        $variant = $this->variant();
        $variant->update(['stock' => 25]);
        $legacyOperation = [];
        $legacyHttp = [];
        $queuedOperation = [];
        $queuedHttp = [];

        app()->instance(
            \App\Services\NotificationService::class,
            \Mockery::mock(\App\Services\NotificationService::class)->shouldIgnoreMissing()
        );
        for ($i = 0; $i < 10; $i++) {
            $operationStarted = microtime(true);
            app(CreateSaleAction::class)->execute($this->actionPayload($variant), $this->manager);
            $operationMs = (microtime(true) - $operationStarted) * 1000;
            $legacyOperation[] = $operationMs;
            // Model two SMTP 535 failures with 10s of assumed waiting each.
            // No external SMTP connection or wall-clock sleep is performed.
            $legacyHttp[] = $operationMs + 20_000;
        }

        app()->instance(\App\Services\NotificationService::class, new \App\Services\NotificationService);
        for ($i = 0; $i < 10; $i++) {
            $started = microtime(true);
            $operationStarted = microtime(true);
            app(CreateSaleAction::class)->execute($this->actionPayload($variant), $this->manager);
            $queuedOperation[] = (microtime(true) - $operationStarted) * 1000;
            $queuedHttp[] = (microtime(true) - $started) * 1000;
        }

        // Simulate a slow, rejected SMTP attempt only after the sale requests
        // have returned; the HTTP timing above must not include this work.
        $queuedSale = Sale::query()->latest('id')->firstOrFail();
        $recipient = NotificationEmail::query()->firstOrFail();
        $failedJob = new SendSaleNotification(
            (int) $queuedSale->id,
            (int) $this->manager->id,
            (int) $recipient->id,
        );
        $smtpStarted = microtime(true);
        Mail::shouldReceive('to')
            ->once()
            ->with($recipient->email)
            ->andReturnUsing(static function (): never {
                usleep(100_000);
                throw new \RuntimeException('Simulated SMTP 535');
            });

        try {
            $failedJob->handle();
            $this->fail('The simulated SMTP rejection should reach the queue worker.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated SMTP 535', $exception->getMessage());
        }
        $simulatedSmtpAttemptMs = (microtime(true) - $smtpStarted) * 1000;
        $this->assertDatabaseHas('sales', ['id' => $queuedSale->id, 'status' => 'completed']);

        $stats = static function (array $values): array {
            sort($values);
            $count = count($values);
            $middle = intdiv($count, 2);

            return [
                'median_ms' => round(($values[$middle - 1] + $values[$middle]) / 2, 2),
                'p95_ms' => round($values[(int) ceil($count * 0.95) - 1], 2),
                'average_ms' => round(array_sum($values) / $count, 2),
            ];
        };

        fwrite(STDERR, "\nSALE_LATENCY_BENCHMARK=".json_encode([
            'legacy_simulated' => ['sale_operation' => $stats($legacyOperation), 'http_total' => $stats($legacyHttp)],
            'queued_database' => ['sale_operation' => $stats($queuedOperation), 'http_total' => $stats($queuedHttp)],
            'samples_each' => 10,
            'smtp_wait_ms_per_sale' => 20000,
            'smtp_failures_per_sale' => 2,
            'simulated_smtp_attempt_ms' => round($simulatedSmtpAttemptMs, 2),
            'jobs_pending_after_benchmark' => DB::table('jobs')->where('queue', 'sale-notifications')->count(),
        ], JSON_UNESCAPED_SLASHES)."\n");
        $this->assertDatabaseCount('sales', 20);
        $this->assertDatabaseCount('jobs', 20);
    }

    public function test_http_checkout_latency_with_fcm_unavailable_benchmark(): void
    {
        if (env('RUN_PUSH_HTTP_BENCHMARK') !== '1') {
            $this->markTestSkipped('Run explicitly with RUN_PUSH_HTTP_BENCHMARK=1.');
        }

        config(['queue.default' => 'database', 'firebase.push_enabled' => true]);
        $firebase = \Mockery::mock(\App\Services\FirebaseMessagingService::class);
        $firebase->shouldNotReceive('send');
        app()->instance(\App\Services\FirebaseMessagingService::class, $firebase);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        $this->api();

        $samples = [];
        foreach (['fcm_available', 'fcm_unavailable'] as $phase) {
            $samples[$phase] = [];
            for ($i = 0; $i < 20; $i++) {
                $variant = $this->variant();
                $started = hrtime(true);
                $this->postJson('/api/sales', $this->salePayload($variant))->assertCreated();
                $samples[$phase][] = (hrtime(true) - $started) / 1_000_000;
            }
        }

        $stats = static function (array $values): array {
            sort($values);

            return [
                'p50_ms' => round(($values[9] + $values[10]) / 2, 2),
                'p95_ms' => round($values[18], 2),
            ];
        };
        fwrite(STDERR, "\nPUSH_HTTP_BENCHMARK=".json_encode([
            'samples_per_phase' => 20,
            'fcm_available' => $stats($samples['fcm_available']),
            'fcm_unavailable' => $stats($samples['fcm_unavailable']),
            'fcm_calls_during_checkout' => 0,
        ])."\n");
        $this->assertDatabaseCount('sales', 40);
        $this->assertDatabaseCount('system_notifications', 40);
        $this->assertDatabaseCount('notification_dispatches', 40);
    }

    public function test_web_and_mobile_checkout_share_the_same_queued_sale_action(): void
    {
        $webVariant = $this->variant();
        $this->actingAs($this->manager)
            ->post(route('sales.store'), $this->salePayload($webVariant))
            ->assertRedirect();
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('jobs', 2);

        $apiVariant = $this->variant();
        $this->api()->postJson('/api/sales', $this->salePayload($apiVariant))->assertCreated();
        $this->assertDatabaseCount('sales', 2);
        $this->assertDatabaseCount('jobs', 4);
        Mail::assertNothingSent();
    }

    public function test_web_checkout_retries_return_the_original_sale_without_new_stock_or_notifications(): void
    {
        $variant = $this->variant();
        $payload = $this->salePayload($variant) + ['operation_key' => 'web-sale-retry-123456789'];

        $first = $this->actingAs($this->manager)->withHeader('X-Inertia', 'true')
            ->post(route('sales.store'), $payload);
        $first->assertRedirect(route('pos.index'));
        $saleId = Sale::query()->sole()->id;
        $first->assertSessionHas('print_sale_id', $saleId);

        $second = $this->post(route('sales.store'), $payload);
        $second->assertRedirect(route('pos.index'));
        $second->assertSessionHas('print_sale_id', $saleId);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('jobs', 2);
        $this->assertSame(4, $variant->fresh()->stock);
        $this->assertDatabaseHas('web_sale_attempts', [
            'user_id' => $this->manager->id,
            'operation_key' => $payload['operation_key'],
            'sale_id' => $saleId,
        ]);

        $changed = $payload;
        $changed['items'][0]['qty'] = 2;
        $this->post(route('sales.store'), $changed)
            ->assertSessionHasErrors('operation_key');
        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(4, $variant->fresh()->stock);
    }

    public function test_legacy_web_sync_uuid_retries_are_atomic_with_the_sale(): void
    {
        $variant = $this->variant();
        $body = [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'payload' => $this->salePayload($variant),
        ];
        $first = $this->actingAs($this->manager)->postJson(route('sync.sales.store'), $body);
        $first->assertOk()->assertJsonPath('status', 'synced');
        $saleId = Sale::query()->sole()->id;
        $first->assertJsonPath('sale_id', $saleId);

        $second = $this->postJson(route('sync.sales.store'), $body);
        $second->assertOk()->assertJsonPath('status', 'synced');
        $second->assertJsonPath('sale_id', $saleId);
        $second->assertHeader('Idempotency-Replayed', 'true');
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sync_receipts', 1);
        $this->assertDatabaseCount('api_operation_receipts', 1);
        $this->assertDatabaseCount('jobs', 2);
        $this->assertSame(4, $variant->fresh()->stock);

        $changed = $body;
        $changed['payload']['items'][0]['qty'] = 2;
        $this->postJson(route('sync.sales.store'), $changed)->assertStatus(409);
        $this->assertDatabaseCount('sales', 1);
    }

    private function api(): static
    {
        $token = $this->manager->createToken('sale-notification-test')->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('Accept', 'application/json');
    }

    private function variant(): ProductVariant
    {
        $category = Category::query()->create(['name' => 'Vestidos '.uniqid(), 'active' => true]);
        $size = Size::query()->create(['name' => 'M '.uniqid(), 'active' => true]);
        $color = Color::query()->create(['name' => 'Negro '.uniqid(), 'hex' => '#000000', 'active' => true]);
        $product = Product::query()->create([
            'sku' => 'SKU-'.strtoupper(uniqid()),
            'name' => 'Producto de prueba',
            'description' => null,
            'category_id' => $category->id,
            'gender' => 'dama',
            'size_id' => $size->id,
            'color_id' => $color->id,
            'purchase_price' => 100,
            'sale_price' => 250,
            'sale_price_base' => 250,
            'status' => 'disponible',
            'created_by' => $this->manager->id,
        ]);

        return ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'VAR-'.strtoupper(uniqid()),
            'size_id' => $size->id,
            'color_id' => $color->id,
            'stock' => 5,
            'purchase_price' => 100,
            'sale_price' => 250,
            'active' => true,
        ]);
    }

    private function actionPayload(ProductVariant $variant): array
    {
        return [
            'items' => [['variant_id' => $variant->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 250]],
            'dinero_recibido' => 250,
        ];
    }

    private function salePayload(ProductVariant $variant): array
    {
        return $this->actionPayload($variant);
    }
}
