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

----------------------------------- Implementation of Renderers -----------------------------------

local labelRenderer = require( 'mw.ext.articlePlaceholder.labelRenderer' ).render

local orderProperties = require( 'mw.ext.articlePlaceholder.orderProperties' ).render

local snaksRenderer = require( 'mw.ext.articlePlaceholder.snaksRenderer' ).render

local referenceRenderer = require( 'mw.ext.articlePlaceholder.referenceRenderer' ).newRenderer( entityrenderer )

local qualifierRenderer = require( 'mw.ext.articlePlaceholder.qualifierRenderer' ).newRenderer( entityrenderer )

local imageStatementRenderer = require( 'mw.ext.articlePlaceholder.imageStatementRenderer' ).newRenderer( entityrenderer )

local statementRenderer = require( 'mw.ext.articlePlaceholder.statementRenderer' ).newRenderer( entityrenderer )

local bestStatementRenderer = require( 'mw.ext.articlePlaceholder.bestStatementRenderer' ).newRenderer( entityrenderer )

local identifierRenderer = require( 'mw.ext.articlePlaceholder.identifierRenderer' ).newRenderer( entityrenderer )

local identifierListRenderer = require( 'mw.ext.articlePlaceholder.identifierListRenderer' ).newRenderer( entityrenderer )

local statementListRenderer = require( 'mw.ext.articlePlaceholder.statementListRenderer' ).newRenderer( entityrenderer )

local topImageRenderer = require( 'mw.ext.articlePlaceholder.topImageRenderer' ).newRenderer( entityrenderer )

local descriptionRenderer = require( 'mw.ext.articlePlaceholder.descriptionRenderer' ).render

-- Render an entity, method to call all renderer
--
-- @param string entityId
-- @return string wikitext
local renderEntity = function ( entityId )
  local entity = mw.wikibase.getEntityObject( entityId )
  local result = ''

  local description = descriptionRenderer( entityId )
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
  local entityId = mw.text.trim( frame.args[1] or "" )
  return renderEntity( entityId )
end

return entityrenderer
