--[[
	@license GNU GPL v2+
	@author Lucie-Aimée Kaffee
]]

local entityrenderer = {}

local libraryUtil = require( 'libraryUtil' )

entityrenderer.imageProperty = 'P6'
local identifierProperties = require( "Identifier" )

-- Get the datavalue for a given property.
-- @param String propertyId
-- @return String datatype
local getDatatype = function( propertyId )
  local property = mw.wikibase.getEntity( propertyId )
  return property['datatype']
end

----------------------------------- Implementation of Renderers -----------------------------------

-- Render a label to the language of the local Wiki.
local labelRenderer = mw.wikibase.label

-- Returns a table of statements sorted by *something*
-- @param table entity
-- @return table of properties
local statementSorter = function( entity )
  -- sort by *something*
  -- limit number of statements
  return entity:getProperties()
end

-- Renders a given table of snaks.
-- @param table snaks
-- @return String result
local snaksRenderer = function( snaks )
  result = ""
  if snaks ~= nil and type( snaks ) == "table" then
    result = result ..  mw.wikibase.renderSnaks( snaks )
  end
  return result
end

-- Render a reference.
-- @param table referenceSnak
-- @return String result
local referenceRenderer = function( referenceSnak )
  local result = ""
  if referenceSnak ~= nil then
    result = result .. "<h4>" .. mw.message.new( 'articleplaceholder-abouttopic-lua-reference' ):plain() .. "</h4>"
    local i = 1
    while referenceSnak[i] do
      for k, v in pairs( referenceSnak[i]['snaks'] ) do
        result = result .. "<p><b>" ..  labelRenderer( k ) .. "</b>: "
        result = result .. snaksRenderer( v ) .. "</p>"
      end
      i = i + 1
    end
  end
  return result
end

-- Render a qualifier.
-- @param table qualifierSnak
-- @return String result
local qualifierRenderer = function( qualifierSnak )
  local result = ""
  if qualifierSnak ~= nil then
    result = result .. "<h4>" .. mw.message.new( 'articleplaceholder-abouttopic-lua-qualifier' ):plain() .. "</h4>"
    for key, value in pairs(qualifierSnak) do
      result = result .. "<p><b>" ..  labelRenderer( key ) .. "</b>: "
      result = result .. snaksRenderer( value ) .. "</p>"
    end
  end
  return result
end

-- Render the image.
-- @param String propertyId
-- @return String renderedImage
local imageStatementRenderer = function( statement, orientationImage )
  local result = ""
  local reference = ""
  local qualifier = ""
  local image = ""
  if statement ~= nil then
    for key, value in pairs( statement ) do
      if key == "mainsnak" then
        image = mw.wikibase.renderSnak( value )
      elseif key == "references" then
        reference = referenceRenderer( value )
      elseif key == "qualifiers" then
        qualifier = qualifierRenderer( value )
      end
    end
  end
  result = "[[File:" .. image .. "|thumb|" .. orientationImage .. "]]"
  result = result .. qualifier ..  reference
  return result
end

-- Renders a statement.
-- @param table statement
-- @return string result
local statementRenderer = function( statement )
  local result = ""
  local reference = ""
  local qualifier = ""
  local mainsnak = ""
  if statement ~= nil then
    for key, value in pairs( statement ) do
      if key == "mainsnak" then
        mainsnak = "<br/><h3>" .. mw.wikibase.renderSnak( value ) .. "</h3><br/>"
      elseif key == "qualifiers" then
        qualifier = qualifierRenderer( value )
      elseif key == "references" then
        reference = referenceRenderer( value )
      end
    end
  end
  result = result .. mainsnak .. qualifier .. reference
  return result
end

-- Renders the best statements.
-- @param table entity
-- @param String propertyId
-- @return string statement
local bestStatementRenderer = function( entity, propertyId )
  local statement = ""
  local bestStatements = entity:getBestStatements( propertyId )
  for _, stat in pairs( bestStatements ) do
    if getDatatype( propertyId ) == "commonsMedia" then
      statement = statement .. imageStatementRenderer( stat, "center" )
    else
      statement = statement .. statementRenderer(stat)
    end
  end
  return statement
end

-- Render the idenfier
-- @return string identifier
local identifierRenderer = function( entity, propertyId )
  return bestStatementRenderer( entity, propertyId )
end

-- Render a list of statements.
-- @param table entity
-- @return string result
local function statementListRenderer ( entity )
  local result = ""
  local properties = statementSorter( entity )
  if properties ~= nil then
    for _, propertyId in pairs( properties ) do

      if identifierProperties[propertyId] then
        result = result .. "<h2>" .. labelRenderer( propertyId ) .. "</h2>"
        result = result .. identifierRenderer( entity, propertyId )

      elseif propertyId ~= entityrenderer.imageProperty then
        result = result .. "<h2>" .. labelRenderer( propertyId ) .. "</h2>"
        result = result .. bestStatementRenderer( entity, propertyId )
      end
    end
  end
  return result
end

-- Render the image.
-- @param String propertyId
-- @return String renderedImage
local topImageRenderer = function( entity, propertyId, orientationImage )
  renderedImage = ""
  imageName = entity:formatPropertyValues( propertyId ).value
  if imageName ~= "" then
    renderedImage = "[[File:" .. imageName .. "|thumb|" .. orientationImage .. "]]"
  end
  return renderedImage
end

-- Render the description.
-- @param String entityId
-- @return String description
local function descriptionRenderer( entityId )
  return mw.wikibase.description( entityId )
end

-- Render an entity, method to call all renderer
-- @param String entityId
-- @return String result
local renderEntity = function ( entityID )
  local entity = mw.wikibase.getEntityObject( entityID )
  local result = ""

  local description = descriptionRenderer( entityID )
  local image = topImageRenderer( entity, entityrenderer.imageProperty, "right" )
  local entityResult = statementListRenderer( entity )

  result = result .. "__NOTOC__"
  if description ~= nil then
    result = result .. mw.message.new( 'articleplaceholder-abouttopic-lua-description' ):plain() ..  description
  end
  result = result .. image
  if entityResult ~= "" then
    result = result .. "<h1>" .. mw.message.new( 'articleplaceholder-abouttopic-lua-entity' ):plain() .. "</h1>" .. entityResult
  end

  return result
end


-------------------------------------------------------------------------------------------------

----------------------------------- Getter und Setter --------------------------------------------
entityrenderer.getLabelRenderer = function()
  return labelRenderer
end

entityrenderer.setLabelRenderer = function( newLabelRenderer )
  util.checkType( 'setLabelRenderer', 1, newLabelRenderer, 'function' )
  labelRenderer = newLabelRenderer
end

entityrenderer.getStatementSorter = function()
  return statementSorter
end

entityrenderer.setStatementSorter = function( newStatementSorter )
  util.checkType( 'setStatementSorter', 1, newStatementSorter, 'function' )
  statementSorter = newStatementSorter
end

entityrenderer.getSnaksRenderer = function()
  return snaksRenderer
end

entityrenderer.setSnaksRenderer = function( newsnaksRenderer )
  util.checkType( 'setSnaksRenderer', 1, newsnaksRenderer, 'function' )
  snaksRenderer = newSnaksRenderer
end

entityrenderer.getReferenceRenderer = function()
  return referenceRenderer
end

entityrenderer.setReferenceRenderer = function( newReferenceRenderer )
  util.checkType( 'setReferenceRenderer', 1, newReferenceRenderer, 'function' )
  referenceRenderer = newReferenceRenderer
end

entityrenderer.getQualifierRenderer = function()
  return qualifierRenderer
end

entityrenderer.setQualifierRenderer = function( newQualifierRenderer )
  util.checkType( 'setQualifierRenderer', 1, newQualifierRenderer, 'function' )
  qualifierRenderer = newQualifierRenderer
end

entityrenderer.getImageStatementRenderer = function()
  return imageStatementRenderer
end

entityrenderer.setImageStatementRenderer = function( newImageStatementRenderer )
  util.checkType( 'setImageStatementRenderer', 1, newImageStatementRenderer, 'function' )
  imageStatementRenderer = newImageStatementRenderer
end

entityrenderer.getStatementRenderer = function()
  return statementRenderer
end

entityrenderer.setStatementRenderer = function( newStatementRenderer )
  util.checkType( 'setStatementRenderer', 1, newStatementRenderer, 'function' )
  statementRenderer = newStatementRenderer
end

entityrenderer.getBestStatementRenderer = function()
  return bestStatementRenderer
end

entityrenderer.setBestStatementRenderer = function( newBestStatementRenderer )
  util.checkType( 'setBestStatementRenderer', 1, newBestStatementRenderer, 'function' )
  bestStatementRenderer = newBestStatementRenderer
end

entityrenderer.getIdentifierRenderer = function()
  return identifierRenderer
end

entityrenderer.setIdentifierRenderer = function( newIdentifierRenderer )
  util.checkType( 'setIdentifierRenderer', 1, newIdentifierRenderer, 'function' )
  identifierRenderer = newIdentifierRenderer
end

entityrenderer.getStatementListRenderer = function()
  return statementListRenderer
end

entityrenderer.setStatementListRenderer = function( newStatementListRenderer )
  util.checkType( 'setStatementListRenderer', 1, newStatementListRenderer, 'function' )
  statementListRenderer = newStatementListRenderer
end

entityrenderer.getTopImageRenderer = function()
  return topImageRenderer
end

entityrenderer.setTopImageRenderer = function( newTopImageRenderer )
  util.checkType( 'setTopImageRenderer', 1, newTopImageRenderer, 'function' )
  topImageRenderer = newTopImageRenderer
end

entityrenderer.getDescriptionRenderer = function()
  return descriptionRenderer
end

entityrenderer.setDescriptionRenderer = function( newDescriptionRenderer )
  util.checkType( 'setDescriptionRenderer', 1, newDescriptionRenderer, 'function' )
  descriptionRenderer = newDescriptionRenderer
end

entityrenderer.getRenderEntity = function()
  return renderEntity
end

entityrenderer.setRenderEntity = function( newRenderEntity )
  util.checkType( 'setRenderEntity', 1, newRenderEntity, 'function' )
  renderEntity = newRenderEntity
end


--------------------------------------------------------------------------------------------------

-- render an entity
entityrenderer.render = function(frame)
  local entityID = mw.text.trim( frame.args[1] or "" )
  return renderEntity( entityID )
end

return entityrenderer
