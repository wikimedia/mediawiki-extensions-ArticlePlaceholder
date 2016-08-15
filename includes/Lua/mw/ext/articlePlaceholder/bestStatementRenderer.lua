--[[
	@license GNU GPL v2+
	@author Lucie-Aimée Kaffee
	@author Marius Hoch
]]

local bestStatementRenderer = {}

-- Get the datavalue for a given property.
--
-- @param string propertyId
-- @return string|nil datatype or nil if property couldn't be loaded
local getDatatype = function( propertyId )
  local property = mw.wikibase.getEntity( propertyId )
  return property and property['datatype']
end

-- Render the best statements from a given entity.
--
-- @param table entity
-- @param string propertyId
-- @return string wikitext
local render = function( self, entity, propertyId )
  local statement = ''
  local bestStatements = entity:getBestStatements( propertyId )
  local imageStatementRenderer = self._entityrenderer:getImageStatementRenderer()
  local statementRenderer = self._entityrenderer:getStatementRenderer()

  for _, stat in pairs( bestStatements ) do
    if getDatatype( propertyId ) == "commonsMedia" then
      statement = statement .. imageStatementRenderer( stat, "left" )
    else
      statement = statement .. statementRenderer(stat)
    end
  end
  return statement
end

-- Get a function which is bound to the given entityrenderer.
--
-- @param table entityrenderer
-- @return function
bestStatementRenderer.newRenderer = function( entityrenderer )
  local self = mw.clone( bestStatementRenderer )
  self._entityrenderer = entityrenderer
  self._render = render

  return function( entity, propertyId )
    return self:_render( entity, propertyId )
  end
end

return bestStatementRenderer
