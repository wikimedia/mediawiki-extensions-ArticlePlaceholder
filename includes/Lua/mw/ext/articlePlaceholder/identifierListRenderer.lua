--[[
	@license GNU GPL v2+
	@author Lucie-Aimée Kaffee
	@author Marius Hoch
]]

local identifierListRenderer = {}

-- Get the datavalue for a given property.
--
-- @param string propertyId
-- @return string|nil datatype or nil if property couldn't be loaded
local getDatatype = function( propertyId )
  local property = mw.wikibase.getEntity( propertyId )
  return property and property['datatype']
end

-- Render the identifier statements from a given entity.
--
-- @param table entity
-- @return string wikitext
local render = function( self, entity )
  local properties = entity:getProperties()
  local identifierList = ''
  if properties ~= nil then
    local labelRenderer = self._entityrenderer:getLabelRenderer()
    local identifierRenderer = self._entityrenderer:getIdentifierRenderer()

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

-- Get a function which is bound to the given entityrenderer.
--
-- @param table entityrenderer
-- @return function
identifierListRenderer.newRenderer = function( entityrenderer )
  local self = mw.clone( identifierListRenderer )
  self._entityrenderer = entityrenderer
  self._render = render

  return function( entity )
    return self:_render( entity )
  end
end

return identifierListRenderer
