<?php

namespace App\Migrations;

use T4\Orm\Migration;

class m_1493378454_createApplication
    extends Migration
{

    public function up()
    {
        $this->db->execute(<<<SQL
CREATE TABLE sources
(
  `__id`    SERIAL PRIMARY KEY,
  name      VARCHAR(100) NOT NULL UNIQUE,
  stream    VARCHAR(100) NOT NULL,
  stream_id BIGINT       NULL,
  INDEX (stream)
);

INSERT INTO sources (name, stream)
VALUES ('Скидка БумЪ', 'Купоны на скидки');

CREATE TABLE events
(
  `__id`          SERIAL PRIMARY KEY,
  saved_id        BIGINT NOT NULL,
  `__source_id`   BIGINT NOT NULL,
  original_id     BIGINT NOT NULL,
  expiration_date DATE   NOT NULL,
  UNIQUE (__source_id, original_id),
  INDEX (expiration_date)
);

CREATE TABLE locations
(
  `__id`   SERIAL PRIMARY KEY,
  name     VARCHAR(255) NOT NULL,
  lat      FLOAT        NOT NULL,
  lng      FLOAT        NOT NULL,
  saved_id BIGINT       NOT NULL,
  INDEX (name)
);

CREATE TABLE events_to_locations
(
  `__id`          SERIAL PRIMARY KEY,
  `__event_id`    BIGINT NOT NULL,
  `__location_id` BIGINT NOT NULL,
  INDEX (`__event_id`),
  INDEX (`__location_id`)
);

CREATE TABLE pictures
(
  `__id`   SERIAL PRIMARY KEY,
  saved_id BIGINT       NOT NULL,
  url      VARCHAR(255) NOT NULL,
  UNIQUE INDEX (url)
);

CREATE TABLE events_to_pictures
(
  `__id`         SERIAL PRIMARY KEY,
  `__event_id`   BIGINT NOT NULL,
  `__picture_id` BIGINT NOT NULL,
  INDEX (`__event_id`),
  INDEX (`__picture_id`)
);

CREATE TABLE locations_to_pictures
(
  `__id`          SERIAL PRIMARY KEY,
  `__location_id` BIGINT NOT NULL,
  `__picture_id`  BIGINT NOT NULL,
  INDEX (`__location_id`),
  INDEX (`__picture_id`)
);
SQL
        );
    }

    public function down()
    {
        echo 'CreateApplication migration is not down-able!';
    }
    
}