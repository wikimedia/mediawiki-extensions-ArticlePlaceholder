local entityrenderer = {}

entityrenderer.imageProperty = 'P6'
entityrenderer.identifierProperties = {}

-- render an entity
entityrenderer.render = function(frame)
  local entityID = mw.text.trim( frame.args[1] or "" )
  local entity = mw.wikibase.getEntityObject( entityID )
  local result = ""

  local description = entityrenderer.descriptionRenderer( entityID )
  local image = entityrenderer.topImageRenderer( entity, entityrenderer.imageProperty, "right" )
  local entityResult = entityrenderer.statementListRenderer( entity )

  result = result .. "Description: " ..  description .. " "
  result = result .. image .. "<br/>"
  result = result .. "<h1>Entity</h1>" .. "<br/>" .. entityResult

  return result
end

-- Get the datavalue for a given property.
local getDatavalue = function( propertyId )
  local property = mw.wikibase.getEntity( propertyId )
  return property['datatype']
end

----------------------------------- Implementation of Renderers -----------------------------------

-- Render a list of statements.
entityrenderer.statementListRenderer = function ( entity )
  local result = ""
  local properties = entityrenderer.statementSorter( entity )
  if properties ~= nil then
    for _, propertyId in pairs( properties ) do
      if propertyId ~= entityrenderer.imageProperty then
        result = result .. "<h2><b>" .. entityrenderer.labelRenderer( propertyId ) .. "</b> </h2>" .. "<br/>"
        result = result .. entityrenderer.renderBestStatements( entity, propertyId )
      end
    end
  end
  return result
end

-- Returns a table of statements sorted by *something*
entityrenderer.statementSorter = function( entity )
  -- sort by *something*
  -- limit number of statements
  return entity:getProperties()
end

-- Renders the best statements.
entityrenderer.renderBestStatements = function( entity, propertyId )
  local statement = ""
  local bestStatements = entity:getBestStatements( propertyId )
  for _, stat in pairs( bestStatements ) do
    if getDatavalue( propertyId ) == "commonsMedia" then
      statement = statement .. entityrenderer.imageRenderer( stat, "center" )
    else
      statement = statement .. entityrenderer.statementRenderer(stat)
    end
  end
  return statement
end

-- Renders a statement.
entityrenderer.statementRenderer = function( statement )
  local result = ""
  local reference = ""
  local qualifier = ""
  local mainsnak = ""
  if statement ~= nil then
    for key, value in pairs( statement ) do
      if key == "mainsnak" then mainsnak = "<h3>" .. mw.wikibase.renderSnak( value ) .. "</h3>"
      elseif key == "references" then reference = "<h4>Reference</h4>" .. "<br/>" .. entityrenderer.referenceRenderer( value )
      elseif key == "qualifiers" then qualifier = "<h4>Qualifier</h4>" .. "<br/>" .. entityrenderer.qualifierRenderer( value )
      end
    end
  end
  result = result .. mainsnak .. reference .. qualifier
  return result
end

-- Render the image.
-- @param String propertyId
-- @return String renderedImage
entityrenderer.imageRenderer = function( statement, orientationImage )
  local result = ""
  local reference = ""
  local qualifier = ""
  local image = ""
  if statement ~= nil then
    for key, value in pairs( statement ) do
      if key == "mainsnak" then image = mw.wikibase.renderSnak( value )
    elseif key == "references" then reference = "<b>Reference</b>"  .. "<br/>" .. entityrenderer.referenceRenderer( value )
  elseif key == "qualifiers" then qualifier = "<b>Qualifier</b>" .. "<br/>" .. entityrenderer.qualifierRenderer( value )
      end
    end
  end
  result = "[[File:" .. image .. "|thumb|" .. orientationImage .. "]] <br/>"
  result = result .. qualifier .. "<br/>" ..  reference
  return result
end

-- Render the image.
-- @param String propertyId
-- @return String renderedImage
entityrenderer.topImageRenderer = function( entity, propertyId, orientationImage )
  imageName = entity:formatPropertyValues( propertyId ).value
  renderedImage = "[[File:" .. imageName .. "|thumb|" .. orientationImage .. "]]"
  return renderedImage
end


-- Render the description.
-- @param String entityId
-- @return String description
entityrenderer.descriptionRenderer = function( entityId )
  return mw.wikibase.description( entityId )
end

-- Render a label to the language of the local Wiki.
-- @param String id
-- @return String label
entityrenderer.labelRenderer = function( entityId )
  return mw.wikibase.label( entityId )
end

-- Render a reference.
entityrenderer.referenceRenderer = function( referenceSnak )
  local result = ""
  if referenceSnak ~= nil then
    local i = 1
    while referenceSnak[i] do
      for k, v in pairs( referenceSnak[i]['snaks'] ) do
        result = result .. "<b>" ..  entityrenderer.labelRenderer( k ) .. "</b>: "
        result = result .. entityrenderer.snakRenderer( v ) .. "<br/>"
      end
      i = i + 1
    end
  end
  return result
end

-- Render a qualifier.
entityrenderer.qualifierRenderer = function( qualifierSnak )
  local result = ""
  if qualifierSnak ~= nil then
    for key, value in pairs(qualifierSnak) do
      result = result .. "<b>" ..  entityrenderer.labelRenderer( key ) .. "</b>: "
      result = result .. entityrenderer.snakRenderer( value ) .. "<br/>"
    end
  end
  return result
end

-- Render a Snak.
entityrenderer.snakRenderer = function( snak )
  local result = ""
  if snak ~= nil and type(snak) == "table" then
    for key, value in pairs( snak ) do
      result = result .. mw.wikibase.renderSnak( value ) .. "<br/>"
    end
  end
  return result
end



----------------------------------------------------------------------

return entityrenderer
