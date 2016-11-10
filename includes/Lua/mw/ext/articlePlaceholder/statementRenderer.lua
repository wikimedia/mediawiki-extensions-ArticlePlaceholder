--[[
	@license GNU GPL v2+
	@author Lucie-Aimée Kaffee
	@author Marius Hoch
]]

local statementRenderer = {}

-- Renders a given statement.
--
-- @param table statement
-- @return string wikitext
local render = function( self, statement )
  local result = ''
  local reference = ''
  local qualifier = ''
  local mainsnak = ''

  local referenceRenderer = self._entityrenderer:getReferenceRenderer()
  local qualifierRenderer = self._entityrenderer:getQualifierRenderer()

  if statement ~= nil then
    for key, value in pairs( statement ) do
      if key == 'mainsnak' then
        mainsnak = mw.wikibase.formatValue( value )
      elseif key == 'references' then
        reference = referenceRenderer( value )
      elseif key == 'qualifiers' then
        qualifier = qualifierRenderer( value )
      end
    end
  end
  return result .. '<div class="articleplaceholder-statement">' .. mainsnak .. reference .. qualifier .. '</div>'
end

-- Get a function which is bound to the given entityrenderer.
--
-- @param table entityrenderer
-- @return function
statementRenderer.newRenderer = function( entityrenderer )
  local self = mw.clone( statementRenderer )
  self._entityrenderer = entityrenderer
  self._render = render

  return function( statement )
    return self:_render( statement )
  end
end

return statementRenderer
