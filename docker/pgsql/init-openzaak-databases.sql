-- Create databases for Open Zaak ecosystem services
-- This script runs during PostgreSQL container initialization

-- Create databases
CREATE DATABASE openzaak;
CREATE DATABASE notificaties;
CREATE DATABASE openforms;
CREATE DATABASE objecttypes;
CREATE DATABASE objects;

-- Connect to each database and enable PostGIS extensions
\c openzaak;
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;

\c notificaties;
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;

\c openforms;
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;

\c objecttypes;
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;

\c objects;
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;