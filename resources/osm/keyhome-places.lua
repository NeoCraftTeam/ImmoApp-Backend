local places = osm2pgsql.define_table({
    name = 'places',
    schema = 'osm_import',
    ids = { type = 'any', id_column = 'osm_id', type_column = 'osm_type' },
    columns = {
        { column = 'name', type = 'text', not_null = true },
        { column = 'display_name', type = 'text' },
        { column = 'place_type', type = 'text', not_null = true },
        { column = 'country_code', type = 'text' },
        { column = 'admin_level', type = 'int' },
        { column = 'tags', type = 'jsonb' },
        { column = 'location', type = 'point', projection = 4326 },
        { column = 'boundary', type = 'multipolygon', projection = 4326 },
    },
})

local boundaries = osm2pgsql.define_table({
    name = 'admin_boundaries',
    schema = 'osm_import',
    ids = { type = 'relation', id_column = 'osm_id' },
    columns = {
        { column = 'name', type = 'text', not_null = true },
        { column = 'country_code', type = 'text' },
        { column = 'admin_level', type = 'int' },
        { column = 'tags', type = 'jsonb' },
        { column = 'boundary', type = 'multipolygon', projection = 4326, not_null = true },
    },
})

local accepted_places = {
    city = true,
    town = true,
    municipality = true,
    village = true,
    suburb = true,
    quarter = true,
    neighbourhood = true,
    locality = true,
}

local function names(tags)
    return tags.name or tags['name:fr'] or tags['name:en']
end

local function place_row(object)
    local place_type = object.tags.place
    local name = names(object.tags)

    if not accepted_places[place_type] or not name then
        return nil
    end

    return {
        name = name,
        display_name = object.tags['name:fr'] or name,
        place_type = place_type,
        country_code = object.tags['addr:country'] or object.tags['ISO3166-1'],
        admin_level = tonumber(object.tags.admin_level),
        tags = object.tags,
    }
end

function osm2pgsql.process_node(object)
    local row = place_row(object)
    if row then
        row.location = object:as_point()
        places:insert(row)
    end
end

function osm2pgsql.process_way(object)
    local row = place_row(object)
    if row and object.is_closed then
        row.boundary = object:as_multipolygon()
        places:insert(row)
    end
end

function osm2pgsql.process_relation(object)
    if object.tags.type ~= 'boundary' and object.tags.type ~= 'multipolygon' then
        return
    end

    local geometry = object:as_multipolygon()
    local row = place_row(object)
    if row then
        row.boundary = geometry
        places:insert(row)
    end

    local admin_level = tonumber(object.tags.admin_level)
    local name = names(object.tags)
    if object.tags.boundary == 'administrative' and admin_level and name then
        boundaries:insert({
            name = name,
            country_code = object.tags['ISO3166-1'] or object.tags['ISO3166-1:alpha2'],
            admin_level = admin_level,
            tags = object.tags,
            boundary = geometry,
        })
    end
end
