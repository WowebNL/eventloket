-- Install PostGIS in the application database.
--
-- No \c to a fixed database name here: the entrypoint already runs this script
-- connected to POSTGRES_DB, which compose fills from DB_DATABASE. Naming the
-- database in this file made the script depend on one specific DB_DATABASE
-- value, so any other value (including the one in .env.example) aborted the
-- whole init with "database ... does not exist" and left the container down.
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;

-- Install PostGIS in the testing database as well. That database is created by
-- the Sail init script mounted before this one, so its name is fixed.
\c testing;
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;
