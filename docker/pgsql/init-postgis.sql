-- Install PostGIS extension in the main database. The entrypoint already runs
-- this script against the database it just created, so it is not named here:
-- naming it would hard-code one value of the configured database name and
-- abort initialisation on any deployment that uses another.
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;

-- Install PostGIS extension in testing database as well
\c testing;
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;
