<?php

namespace Oneup\UploaderBundle\Tests\Controller;

use Symfony\Component\EventDispatcher\Event;
use Oneup\UploaderBundle\Tests\Controller\AbstractUploadTest;
use Oneup\UploaderBundle\UploadEvents;
use Oneup\UploaderBundle\Event\PostChunkUploadEvent;

abstract class AbstractChunkedUploadTest extends AbstractUploadTest
{
    protected $total = 6;
    
    abstract protected function getNextRequestParameters($i);
    abstract protected function getNextFile($i);
    
    public function testChunkedUpload()
    {
        // assemble a request
        $client = $this->client;
        $endpoint = $this->helper->endpoint($this->getConfigKey());
        
        for($i = 0; $i < $this->total; $i ++) {
            $client->request('POST', $endpoint, $this->getNextRequestParameters($i), array($this->getNextFile($i)));
            $response = $client->getResponse();
        
            $this->assertTrue($response->isSuccessful());
            $this->assertEquals($response->headers->get('Content-Type'), 'application/json');
        }
        
        foreach($this->getUploadedFiles() as $file) {
            $this->assertTrue($file->isFile());
            $this->assertTrue($file->isReadable());
            $this->assertEquals(120, $file->getSize());
        }
    }
    
    public function testEvents()
    {
        $endpoint = $this->helper->endpoint($this->getConfigKey());
        
        // prepare listener data
        $me = $this;
        $chunkCount = 0;
        $uploadCount = 0;
        $chunkSize = $this->getNextFile(0)->getSize();
        
        for($i = 0; $i < $this->total; $i ++) {
            // each time create a new client otherwise the events won't get dispatched
            $client = static::createClient();
            $dispatcher = $client->getContainer()->get('event_dispatcher');
            
            $dispatcher->addListener(UploadEvents::POST_CHUNK_UPLOAD, function(PostChunkUploadEvent $event) use (&$chunkCount, $chunkSize, &$me) {
                ++ $chunkCount;
                
                $chunk = $event->getChunk();
                
                $me->assertEquals($chunkSize, $chunk->getSize());
            });
            
            $dispatcher->addListener(UploadEvents::POST_UPLOAD, function(Event $event) use (&$uploadCount) {
                ++ $uploadCount;
            });
            
            $client->request('POST', $endpoint, $this->getNextRequestParameters($i), array($this->getNextFile($i)));
        }

        $this->assertEquals($this->total, $chunkCount);
        $this->assertEquals(1, $uploadCount);
    }
}