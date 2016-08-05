--[[
	@license GNU GPL v2+
	@author Lucie-Aimée Kaffee
]]

local entityrenderer = {}

local util = require( 'libraryUtil' )
local php = mw_interface

entityrenderer.imageProperty = php.getImageProperty()
entityrenderer.referencesBlacklist = php.getReferencesBlacklist()

local hasReferences = false

-- Get the datavalue for a given property.
-- @param string propertyId
-- @return string|nil datatype or nil if property couldn't be loaded
local getDatatype = function( propertyId )
  local property = mw.wikibase.getEntity( propertyId )
  return property and property['datatype']
end

----------------------------------- Implementation of Renderers -----------------------------------

-- Render a label to the language of the local Wiki.
-- @param string entityId
-- @return wikitext label or entityId if no label available
local labelRenderer = function( entityId )
  local label = mw.wikibase.label( entityId )
  if label ~= nil then
    label = mw.text.nowiki( label )
  else
    label = '[' .. mw.wikibase.getEntityUrl( entityId ) .. ' ' .. entityId .. ']'
  end
  return label
end

-- Returns a table of ordered property IDs
-- @param table entity
-- @return table of property IDs
local orderProperties = function( entity )
  local propertyIDs = entity:getProperties()
  return mw.wikibase.orderProperties( propertyIDs )
end

-- Renders a given table of snaks.
-- @param table snaks
-- @return String result
local snaksRenderer = function( snaks )
  local result = ''
  if snaks ~= nil and type( snaks ) == 'table' then
    result = mw.wikibase.renderSnaks( snaks )
  end
  return result
end

-- Remove the references that include a Snak with the blacklisted property id.
-- @param table references
-- @return table newRefs
local removeBlacklistedReferences = function( references )
  local newRefs = {}

  for key, reference in pairs(references) do
    local blacklisted = false
    for propRef, snakRef in pairs( reference['snaks'] ) do

      if propRef == entityrenderer.referencesBlacklist then
        blacklisted = true
        break
      end
    end
    if blacklisted == false then
      table.insert( newRefs, reference )
    end
  end

  return newRefs
end

-- Render a reference.
-- @param table references
-- @return String result
local referenceRenderer = function( references )
  local frame = mw:getCurrentFrame()
  local referencesWikitext = {}

  if entityrenderer.referencesBlacklist ~= nil then
    references = removeBlacklistedReferences( references )
  end

  local i, reference = next( references, nil )

  if i then
    hasReferences = true

    while i do
      referenceWikitext = snaksRenderer( reference['snaks'] )
      table.insert( referencesWikitext, frame:extensionTag( 'ref', referenceWikitext ) )
      i, reference = next( references, i )
    end
  end

  return table.concat( referencesWikitext, "" )
end

-- Render a qualifier.
-- @param table qualifierSnak
-- @return String result
local qualifierRenderer = function( qualifierSnak )
  local result = ''
  if qualifierSnak ~= nil then
    for key, value in pairs(qualifierSnak) do
      result = result .. '<div class="articleplaceholder-qualifier"><p>' .. labelRenderer( key ) .. ': '
      result = result .. snaksRenderer( value ) .. '</p></div>'
    end
  end
  return result
end

-- Render a statement containing images.
-- @param table statement
-- @param String orientationImage
-- @param bool inlineQualifiers, default false
-- @return String renderedImage
local imageStatementRenderer = function( statement, orientationImage, inlineQualifiers )
  local inlineQualifiers = inlineQualifiers or false
  local reference = ''
  local qualifier = ''
  local image = ''

  if statement ~= nil then
    for key, value in pairs( statement ) do
      if key == 'mainsnak' then
        image = mw.wikibase.renderSnak( value )
      elseif key == 'references' then
        reference = referenceRenderer( value )
      elseif key == 'qualifiers' then
        qualifier = qualifierRenderer( value )
      end
    end
  end
  local result = '[[File:' .. image .. '|thumb|' .. orientationImage .. '|340x280px|'
  if inlineQualifiers == true then
    result = result .. reference .. ' ' .. qualifier .. ']]'
  else
    result = result .. reference .. ' ' .. ']]' .. qualifier
  end
  return result
end

-- Renders a statement.
-- @param table statement
-- @return string result
local statementRenderer = function( statement )
  local result = ''
  local reference = ''
  local qualifier = ''
  local mainsnak = ''
  if statement ~= nil then
    for key, value in pairs( statement ) do
      if key == 'mainsnak' then
        mainsnak = mw.wikibase.renderSnak( value )
      elseif key == 'references' then
        reference = referenceRenderer( value )
      elseif key == 'qualifiers' then
        qualifier = qualifierRenderer( value )
      end
    end
  end
  result = result .. '<div class="articleplaceholder-statement"><p><span class="articleplaceholder-value">' .. mainsnak .. '</span>' .. reference .. '</p></div>' .. qualifier
  return result
end

-- Renders the best statements.
-- @param table entity
-- @param String propertyId
-- @return string statement
local bestStatementRenderer = function( entity, propertyId )
  local statement = ''
  local bestStatements = entity:getBestStatements( propertyId )
  for _, stat in pairs( bestStatements ) do
    if getDatatype( propertyId ) == "commonsMedia" then
      statement = statement .. imageStatementRenderer( stat, "left" )
    else
      statement = statement .. statementRenderer(stat)
    end
  end
  return statement
end

-- Render the identifier
-- @return string identifier
local identifierRenderer = function( entity, propertyId )
  return bestStatementRenderer( entity, propertyId )
end

-- Render a list of identifier
-- @param table entity
-- @return string identifier
local identifierListRenderer = function( entity )
  local properties = entity:getProperties()
  local identifierList = ''
  if properties ~= nil then
    for _, propertyId in pairs( properties ) do
      if getDatatype( propertyId ) == "external-id" then
        identifierList = identifierList .. '<tr><td class="articleplaceholder-id-prop">' .. labelRenderer( propertyId ) .. '</td>'
        identifierList = identifierList .. '<td class="articleplaceholder-id-value">' .. identifierRenderer( entity, propertyId ) .. '</td></tr>'
      end
    end
  end
  if identifierList ~= nil and identifierList ~= '' then
    identifierList = '<table>' .. identifierList .. '</table>'
    identifierList = '<h2>' .. mw.message.new( 'articleplaceholder-abouttopic-lua-identifier' ):plain() .. '</h2>' ..  identifierList
    return '<div class="articleplaceholder-identifierlist">' .. identifierList .. '</div>'
  end
  return ''
end

-- Render a list of statements.
-- @param table entity
-- @return string result
local statementListRenderer = function( entity )
  local result = ''
  local propertyIDs = orderProperties( entity )

  if propertyIDs ~= nil then

    for i=1, #propertyIDs do
      if propertyIDs[i] ~= entityrenderer.imageProperty and getDatatype( propertyIDs[i] ) ~= "external-id" then
        result = result .. '<div class="articleplaceholder-statementgroup">'
        -- check if the label is 'coordinates' and upper case it
        -- this is necessary since headings will be rendered to id="*label*"
        -- and 'coordinates' has specific CSS values on most mayor Wikipedias
        local label = labelRenderer( propertyIDs[i] )
        if label == 'coordinates' then
          label = label:gsub("^%l", string.upper)
        end

        result = result .. '<h2>' .. label .. '</h2>'
        result = result .. bestStatementRenderer( entity, propertyIDs[i] )
        result = result .. '</div>'
      end
    end
  end

  return '<div class="articleplaceholder-statementgrouplist">' .. result .. '</div>'
end

-- Render the image.
-- @param table entity
-- @param string propertyId
-- @param string orientationImage
-- @return string renderedImage
local topImageRenderer = function( entity, propertyId, orientationImage )
  local renderedImage = ''

  imageStatement = entity:getBestStatements( propertyId )[1]

  if imageStatement ~= nil then
    renderedImage = imageStatementRenderer( imageStatement, orientationImage, true )
    renderedImage = '<div class="articleplaceholder-topimage">' .. renderedImage .. '</div>'
  end

  return renderedImage
end

-- Render the description.
-- @param String entityId
-- @return String description
local function descriptionRenderer( entityId )
  local description = mw.wikibase.description( entityId )

  if description ~= nil then
    return '<div class="articleplaceholer-description"><p>' .. description .. '</p></div>'
  else
    return ''
  end
end

-- Render an entity, method to call all renderer
-- @param String entityId
-- @return String result
local renderEntity = function ( entityID )
  local entity = mw.wikibase.getEntityObject( entityID )
  local result = ''

  local description = descriptionRenderer( entityID )
  local image = topImageRenderer( entity, entityrenderer.imageProperty, "right" )
  local identifier = identifierListRenderer( entity )
  local entityResult = statementListRenderer( entity )

  result = result .. '__NOTOC__'
  if description ~= nil then
    result = result .. description
  end
  result = result .. '<div class="articleplaceholder-sidebar">' .. image
  result = result .. identifier .. '</div>'
  if entityResult ~= '' then
    result = result .. entityResult
  end

  if hasReferences then
    result = result .. '<h2>' .. mw.message.new( 'articleplaceholder-abouttopic-lua-reference' ):plain() .. '</h2>'
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

entityrenderer.getOrderProperties = function()
  return orderProperties
end

entityrenderer.setOrderProperties = function( newOrderProperties )
  util.checkType( 'setOrderProperties', 1, newOrderProperties, 'function' )
  orderProperties = newOrderProperties
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

entityrenderer.getIdentifierListRenderer = function()
  return identifierListRenderer
end

entityrenderer.setIdentifierListRenderer = function( newIdentifierListRenderer )
  util.checkType( 'setIdentifierListRenderer', 1, newIdentifierListRenderer, 'function' )
  identifierListRenderer = newIdentifierListRenderer
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

entityrenderer.getHasReferences = function()
  return hasReferences
end

entityrenderer.setHasReferences = function( newHasReferences )
  util.checkType( 'setHasReferences', 1, newHasReferences, 'boolean' )
  hasReferences = newHasReferences
end

--------------------------------------------------------------------------------------------------

-- render an entity
entityrenderer.render = function(frame)
  local entityID = mw.text.trim( frame.args[1] or "" )
  return renderEntity( entityID )
end

return entityrenderer
