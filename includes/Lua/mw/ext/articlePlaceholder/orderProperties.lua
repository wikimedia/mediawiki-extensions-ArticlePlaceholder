--[[
	@license GNU GPL v2+
	@author Lucie-Aimée Kaffee
	@author Marius Hoch
]]

local orderProperties = {}

-- Returns a table of ordered property IDs.
--
-- @param table entity
-- @return table of property IDs
orderProperties.render = function( entity )
  local propertyIDs = entity:getProperties()
  return mw.wikibase.orderProperties( propertyIDs )
end

return orderProperties
