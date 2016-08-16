--[[
	@license GNU GPL v2+
	@author Lucie-Aimée Kaffee
	@author Marius Hoch
]]

local qualifierRenderer = {}

-- Render a qualifier.
--
-- @param table qualifierSnak
-- @return string wikitext
local render = function( self, qualifierSnak )
  local result = ''
  local labelRenderer = self._entityrenderer:getLabelRenderer()
  local snaksRenderer = self._entityrenderer:getSnaksRenderer()

  if qualifierSnak ~= nil then
    for key, value in pairs(qualifierSnak) do
      result = result .. '<div class="articleplaceholder-qualifier"><p>' .. labelRenderer( key ) .. ': '
      result = result .. snaksRenderer( value ) .. '</p></div>'
    end
  end
  return result
end

-- Get a function which is bound to the given entityrenderer.
--
-- @param table entityrenderer
-- @return function
qualifierRenderer.newRenderer = function( entityrenderer )
  local self = mw.clone( qualifierRenderer )
  self._entityrenderer = entityrenderer
  self._render = render

  return function( qualifierSnak )
    return self:_render( qualifierSnak )
  end
end

return qualifierRenderer
