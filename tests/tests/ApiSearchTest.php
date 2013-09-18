<?php



class ApiSearchTest extends CDbTestCase
{
    public function setUp()
    {
        $this->getFixtureManager()->resetTable('article');
        $this->getFixtureManager()->loadFixture('article');

        exec('./setup.sh');

        parent::setUp();
    }


    /**
     * @return ESphinxApiConnection
     */
    protected function createConnection()
    {
        $sphinx = new ESphinxApiConnection;
        $sphinx->setServer(array('localhost', 9877));
        $sphinx->init();

        return $sphinx;
    }

    public function testCreate()
    {
        $sphinx = $this->createConnection();
        $this->assertInstanceOf('ESphinxApiConnection', $sphinx);
        $this->assertFalse($sphinx->getIsConnected());
    }

    public function testSimpleQuery()
    {
        $sphinx = $this->createConnection();

        $query = new ESphinxQuery('First Article with Title', 'article', array(
            'matchMode' => ESphinxMatch::PHRASE,
        ));

        $result = $sphinx->executeQuery($query);
        $this->assertInstanceOf('ESphinxResult', $result);
        $this->assertEquals($result->getFound(), 1);

        /** @var ESphinxMatchResult $math */
        $math = $result[0];

        $this->assertEquals($math->getId(), 1);
        $this->assertEquals($math->getAttribute('id'), 1);
    }

    public function testQueryWithParams()
    {
        $sphinx = $this->createConnection();

        $query = new ESphinxQuery('Article with Title', 'article', array(
            'filters' => array(
                array('user_id', array(1000, 2000))
            )
        ));
        $result = $sphinx->executeQuery($query);
        $this->assertInstanceOf('ESphinxResult', $result);

        $this->assertEquals($result->getFound(), 2);
    }

    public function testQueries()
    {
        $sphinx = $this->createConnection();

        $query1 = new ESphinxQuery('Article with Title', 'article', array('filters' => array(
            array('user_id', array(1000, 2000))
        )));
        $query2 = new ESphinxQuery('Article with Title', 'article', array('filters' => array(
            array('user_id', array(3000, 4000))
        )));

        $result = $sphinx->executeQueries(array($query1, $query2));
        $this->assertCount(2, $result);

        $result1 = $result[0];
        $result2 = $result[1];

        $this->assertEquals($result1->getFoundTotal(), 2);
        $this->assertEquals($result2->getFoundTotal(), 3);

        $this->assertEquals($result1[0]->id, 1);
        $this->assertEquals($result1[1]->id, 2);

        $this->assertEquals($result2[0]->id, 3);
        $this->assertEquals($result2[1]->id, 4);
        $this->assertEquals($result2[2]->id, 5);
    }



    public function testFilters()
    {
        $sphinx = $this->createConnection();

        $query1 = new ESphinxQuery('Article with Title', 'article', array(
            'filters'      => array(array('user_id', array(1000, 2000))),
            'rangeFilters' => array(array('rating', 'min' => 1.4, 'max' => 1.4)),
        ));

        $result = $sphinx->executeQuery($query1);
        $this->assertEquals($result->getFound(), 1);
    }

    public function testIdFilter()
    {
        $sphinx = $this->createConnection();

        $query = new ESphinxQuery('', 'article', array(
            'minId' => 2,
            'maxId' => 3,
        ));

        $result = $sphinx->executeQuery($query);
        $this->assertEquals($result->getFound(), 2);

        $this->assertEquals($result[0]->id, 2);
        $this->assertEquals($result[1]->id, 3);
    }

    public function testSimpleSort()
    {
        $sphinx = $this->createConnection();

        $criteria = new ESphinxSearchCriteria;
        $criteria->sortMode = ESphinxSort::ATTR_DESC;
        $criteria->setSortBy('user_id');

        $query = new ESphinxQuery('', 'article', $criteria);
        $result = $sphinx->executeQuery($query);

        $this->assertEquals($result->getFound(), 5);

        $this->assertEquals($result[0]->id, 4);
        $this->assertEquals($result[1]->id, 5);
        $this->assertEquals($result[2]->id, 3);
        $this->assertEquals($result[3]->id, 2);
        $this->assertEquals($result[4]->id, 1);

        $criteria->sortMode = ESphinxSort::ATTR_ASC;

        $query = new ESphinxQuery('', 'article', $criteria);
        $result = $sphinx->executeQuery($query);

        $this->assertEquals($result->getFound(), 5);

        $this->assertEquals($result[0]->id, 1);
        $this->assertEquals($result[1]->id, 2);
        $this->assertEquals($result[2]->id, 3);
        $this->assertEquals($result[3]->id, 4);
        $this->assertEquals($result[4]->id, 5);
    }

    public function testExtendedSort()
    {
        $sphinx = $this->createConnection();
        $criteria = new ESphinxSearchCriteria;
        $criteria->sortMode = ESphinxSort::EXTENDED;

        $criteria->addOrder('user_id', 'ASC');
        $criteria->addOrder('id', 'DESC');

        $query = new ESphinxQuery('', 'article', $criteria);
        $result = $sphinx->executeQuery($query);

        $this->assertEquals($result->getFound(), 5);

        $this->assertEquals($result[0]->id, 1);
        $this->assertEquals($result[1]->id, 2);
        $this->assertEquals($result[2]->id, 3);
        $this->assertEquals($result[3]->id, 5);
        $this->assertEquals($result[4]->id, 4);
    }

    public function testGroupBy()
    {
        $sphinx = $this->createConnection();
        $criteria = new ESphinxSearchCriteria;
        $criteria->groupBy = 'user_id';
        $criteria->groupBySort = '@count desc';

        $query = new ESphinxQuery('', 'article', $criteria);
        $result = $sphinx->executeQuery($query);

        $this->assertEquals(4, $result->getFound());
        $first = $result[0];


        $this->assertEquals(4000, $first->user_id);
        $this->assertEquals(2, $first->{'@count'});
    }

    public function testLimit()
    {
        $sphinx = $this->createConnection();

        $query = new ESphinxQuery('', 'article', array('limit' => 2));
        $result = $sphinx->executeQuery($query);

        $this->assertEquals(2, count($result));
    }

    public function testMultiQuery()
    {
        $sphinx = $this->createConnection();

        $query1 = new ESphinxQuery('first', 'article', array('limit' => 1));
        $query2 = new ESphinxQuery('second', 'article', array('limit' => 1));


        $result = $sphinx->executeQueries(array($query1, $query2));
        $this->assertCount(2, $result);

        $first  = $result[0][0];
        $second = $result[1][0];

        $this->assertEquals(1, $first->id);
        $this->assertEquals(2, $second->id);
    }

    public function testAddQuery()
    {
        $sphinx = $this->createConnection();

        try {
            $sphinx->runQueries();
            $this->setExpectedException('ESphinxException');
        } catch (Exception $e) {
            $this->assertInstanceOf('ESphinxException', $e);
        }

        $sphinx->addQuery(new ESphinxQuery('first', 'article', array('limit' => 1)));
        $sphinx->addQuery(new ESphinxQuery('second', 'article', array('limit' => 1)));

        $result = $sphinx->runQueries();

        $this->assertCount(2, $result);

        $first  = $result[0][0];
        $second = $result[1][0];

        $this->assertEquals(1, $first->id);
        $this->assertEquals(2, $second->id);
    }
}