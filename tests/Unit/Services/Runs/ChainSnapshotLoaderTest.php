<?php

namespace Tests\Unit\Services\Runs;

use App\Models\Chain;
use App\Models\ChainNode;
use App\Services\Runs\ChainSnapshotLoader;
use PHPUnit\Framework\TestCase;

class ChainSnapshotLoaderTest extends TestCase
{
    public function testCreateSnapshotUsesLoadedNodesAndOrdersThem(): void
    {
        $loader = new ChainSnapshotLoader();

        $first = new ChainNode();
        $first->setAttribute('id', 1);
        $first->setAttribute('name', 'B');
        $first->setAttribute('order_index', 2);

        $second = new ChainNode();
        $second->setAttribute('id', 2);
        $second->setAttribute('name', 'A');
        $second->setAttribute('order_index', 1);

        $chain = new Chain();
        $chain->setRelation('nodes', collect([$first, $second]));

        $snapshot = $loader->createSnapshot($chain);

        $this->assertCount(2, $snapshot);
        $this->assertSame(2, $snapshot[0]['id']);
        $this->assertSame(1, $snapshot[1]['id']);
    }
}
