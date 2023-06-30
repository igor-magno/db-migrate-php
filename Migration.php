<?php

abstract class Migration
{
    protected $connection;
    
    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
    }
    
    abstract public function up();
    
    abstract public function down();
}
