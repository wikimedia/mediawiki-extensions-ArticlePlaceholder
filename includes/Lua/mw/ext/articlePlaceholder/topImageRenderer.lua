--[[
	@license GNU GPL v2+
	@author Lucie-Aimée Kaffee
	@author Marius Hoch
]]

local topImageRenderer = {}

-- Render the best image statement with a certain property id on a given entity.
--
-- @param table entity
-- @param string propertyId
-- @param string orientationImage
--
-- @return string wikitext
local render = function( self, entity, propertyId, orientationImage )
  local renderedImage = ''

  imageStatement = entity:getBestStatements( propertyId )[1]

  if imageStatement ~= nil then
    local imageStatementRenderer = self._entityrenderer:getImageStatementRenderer()

    renderedImage = imageStatementRenderer( imageStatement, orientationImage, true )
    renderedImage = '<div class="articleplaceholder-topimage">' .. renderedImage .. '</div>'
  end

  return renderedImage
end

-- Get a function which is bound to the given entityrenderer.
--
-- @param table entityrenderer
-- @return function
topImageRenderer.newRenderer = function( entityrenderer )
  local self = mw.clone( topImageRenderer )
  self._entityrenderer = entityrenderer
  self._render = render

  return function( entity, propertyId, orientationImage )
    return self:_render( entity, propertyId, orientationImage )
  end
end

return topImageRenderer
