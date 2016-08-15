--[[
	@license GNU GPL v2+
	@author Lucie-Aimée Kaffee
	@author Marius Hoch
]]

local identifierRenderer = {}

-- Render a given identifier
--
-- @param table entity
-- @param string propertyId
-- @return string wikitext
local render = function( self, entity, propertyId )
  local bestStatementRenderer = self._entityrenderer.getBestStatementRenderer()

  return bestStatementRenderer( entity, propertyId )
end

-- Get a function which is bound to the given entityrenderer.
--
-- @param table entityrenderer
-- @return function
identifierRenderer.newRenderer = function( entityrenderer )
  local self = mw.clone( identifierRenderer )
  self._entityrenderer = entityrenderer
  self._render = render

  return function( entity, propertyId )
    return self:_render( entity, propertyId )
  end
end

return identifierRenderer
