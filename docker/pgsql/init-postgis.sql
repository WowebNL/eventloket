-- PostGIS extensions for the testing database
-- Note: The testing database is created by Sail's 10-create-testing-database.sql
-- The main database gets PostGIS from the postgis/postgis image's 10_postgis.sh
\c testing;
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;