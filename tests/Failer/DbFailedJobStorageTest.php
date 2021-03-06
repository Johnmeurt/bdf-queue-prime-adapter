<?php

namespace Bdf\Queue\Failer;

use Bdf\Prime\Connection\ConnectionInterface;
use Bdf\Prime\Prime;
use Bdf\Queue\Message\QueuedMessage;
use Bdf\Queue\Tests\PrimeTestCase;
use PHPUnit\Framework\TestCase;

/**
 * @group Bdf_Queue
 * @group Bdf_Queue_Failed
 */
class DbFailedJobStorageTest extends TestCase
{
    use PrimeTestCase;

    /**
     * @var DbFailedJobStorage
     */
    protected $provider;
    
    /**
     * 
     */
    protected function setUp(): void
    {
        $this->configurePrime();

        $this->provider = DbFailedJobStorage::make(Prime::service(), ['connection' => 'test', 'table' => 'failed']);
        $this->provider->schema()->flush();
    }
    
    /**
     * 
     */
    protected function tearDown(): void
    {
        $this->provider->schema()->drop('failed')->flush();
    }
    
    /**
     *
     */
    public function test_connection()
    {
        $this->assertInstanceOf(ConnectionInterface::class, $this->provider->connection());
    }
    
    /**
     *
     */
    public function test_create_log()
    {
        $message = new QueuedMessage();
        $message->setName('job');
        $message->setConnection('queue-connection');
        $message->setQueue('queue');

        $created = FailedJob::create($message, new \Exception('foo'));

        $this->assertSame(null, $created->id);
        $this->assertSame($message->name(), $created->name);
        $this->assertSame($message->connection(), $created->connection);
        $this->assertSame($message->queue(), $created->queue);
        $this->assertSame($message->toQueue(), $created->messageContent);
        $this->assertSame(QueuedMessage::class, $created->messageClass);
        $this->assertSame('foo', $created->error);
        $this->assertInstanceOf(\DateTime::class, $created->failedAt);
    }

    /**
     *
     */
    public function test_to_message()
    {
        $message = new QueuedMessage();
        $message->setName('job');
        $message->setConnection('queue-connection');
        $message->setQueue('queue');

        $created = FailedJob::create($message, new \Exception('foo'));

        $this->assertEquals($message, $created->toMessage());
    }

    /**
     *
     */
    public function test_store_log()
    {
        $message = new QueuedMessage();
        $message->setName('job');
        $message->setConnection('queue-connection');
        $message->setQueue('queue');

        $created = FailedJob::create($message, new \Exception('foo'));

        $this->provider->store($created);
        $job = $this->provider->find(1);

        $this->assertSame('1', $job->id);
        $this->assertSame($created->name, $job->name);
        $this->assertSame($created->connection, $job->connection);
        $this->assertSame($created->queue, $job->queue);
        $this->assertSame($message->toQueue(), $created->messageContent);
        $this->assertSame(QueuedMessage::class, $created->messageClass);
        $this->assertSame($created->error, $job->error);
        $this->assertInstanceOf(\DateTime::class, $job->failedAt);
    }

    /**
     * 
     */
    public function test_all()
    {
        $this->provider->store(new FailedJob([
            'connection' => 'queue-connection',
            'queue' => 'queue1',
        ]));
        $this->provider->store(new FailedJob([
            'connection' => 'queue-connection',
            'queue' => 'queue2',
        ]));

        $jobs = $this->provider->all();
        $jobs->load();

        $this->assertSame(2, count($jobs));
        $this->assertSame('queue1', $jobs[0]->queue);
        $this->assertSame('queue2', $jobs[1]->queue);
    }
    
    /**
     * 
     */
    public function test_forget()
    {
        $this->provider->store(new FailedJob([
            'connection' => 'queue-connection',
            'queue' => 'queue1',
        ]));
        $this->provider->store(new FailedJob([
            'connection' => 'queue-connection',
            'queue' => 'queue2',
        ]));
        
        $result = $this->provider->forget(1);
        $jobs = $this->provider->all();
        $jobs->load();
        
        $this->assertTrue($result);
        $this->assertSame(1, count($jobs));
    }
    
    /**
     *
     */
    public function test_flush()
    {
        $this->provider->store(new FailedJob([
            'connection' => 'queue-connection',
            'queue' => 'queue1',
        ]));
        $this->provider->store(new FailedJob([
            'connection' => 'queue-connection',
            'queue' => 'queue2',
        ]));
        
        $this->provider->flush();
        
        $this->assertSame(0, count($this->provider->all()));
    }
}